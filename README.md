# DataScienceTool

An internal web app for turning messy data files into clean, validated deliveries.
Upload a file, review a data-quality report, build a cleaning pipeline, validate the
result against a team's schema contract, and export it in the format that team needs,
with every step recorded so any run can be inspected or replayed later.

## How it works

```text
┌──────────────────────────────┐   HTTP + X-Internal-Key   ┌──────────────────────────┐
│ Laravel + Vue (Inertia)      │ ────────────────────────► │ Python service (FastAPI) │
│ UI, auth, roles, contracts,  │ ◄──────────────────────── │ profile, clean, validate,│
│ runs, queue jobs, deliveries │        JSON results       │ export, PDF extract      │
└──────────────┬───────────────┘                           └────────────┬─────────────┘
               │                                                        │
        Database + queue                                  Shared file storage
                                                        (uploads, cleaned, exports)
```

**Laravel owns state and workflow; Python is stateless.** Laravel stores runs, steps,
contracts and deliveries, and queues the work. The Python service receives a file path
inside shared storage, processes it with pandas, and returns the result.

### The workflow

| Step | What happens | Run status |
| --- | --- | --- |
| 1. Upload | File is stored and a profiling job is queued | `pending` → `profiling` |
| 2. Profile | Python returns a quality report: nulls, duplicates, types, outliers, distributions | `profiled` |
| 3. Clean | You build an ordered pipeline of steps and preview it; applying it writes a cleaned file and re-profiles it | `cleaning` → `cleaned` |
| 4. Validate | The cleaned data is checked against a schema contract | `validated` or `validation_failed` |
| 5. Export | The data is written as CSV, JSON or Parquet and a delivery is recorded | `exporting` → `delivered` |

Any step that fails for good sets the run to `failed`; re-profile it to continue.

**Cleaning steps:** drop nulls, drop columns, fill nulls (mean, median, mode or a value),
handle outliers (cap or filter), remove duplicates, normalise dates, normalise text,
rename columns, regex extract, and filter rows by a condition such as
`age >= 18 and country in ['CM', 'NG']`.

### Rules the app enforces

- **The original upload is never modified.** Cleaning always starts from the original
  file with the full pipeline, so editing the step list and re-applying replaces the
  previous result. Each step records the file it read and the file it wrote.
- **Export requires passing validation** against the chosen contract, with no cleaning
  since. Otherwise an override reason is required and is stored on the export step.
- **Access control:**

  | Role | Can do |
  | --- | --- |
  | `superadmin` | Everything, including managing users and contracts |
  | `operator` | Upload runs and process runs they created |
  | `viewer` | View runs and deliveries |

  Validating or exporting against a contract also requires being assigned to that
  contract with "can run". Superadmins manage these assignments under **Admin → Users**.

## Tech stack

- **Web app:** Laravel 13, PHP 8.4, Inertia + Vue 3, Tailwind CSS, Breeze auth,
  spatie/laravel-permission
- **Processing:** Python 3.11+, FastAPI, pandas, pdfplumber
- **Tests:** Pest (Laravel), pytest (Python), Pint for code style
- **Runtime:** Docker Compose with nginx/PHP-FPM, a queue worker, the Python service,
  MySQL and Redis; or SQLite and a database queue for local development

## Getting started

### Option A: Docker

1. Copy the env file and set a shared secret:

   ```sh
   cp .env.example .env
   python -c "import secrets; print(secrets.token_hex(32))"   # paste as INTERNAL_KEY in .env
   ```

2. Start everything and set up the app:

   ```sh
   docker compose up -d --build
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```

3. Open <http://localhost:8080>.

MySQL, Redis and the Python service are only published on `127.0.0.1`, so they are
not reachable from the network.

### Option B: Local development

You need PHP 8.4, Composer, Node 22 and Python 3.11+.

1. **Laravel app** (SQLite and the database queue by default):

   ```sh
   composer setup               # installs dependencies, creates .env, migrates, builds assets
   php artisan db:seed
   ```

2. **Python service:**

   ```sh
   cd python-service
   python -m venv venv
   venv/Scripts/activate        # Windows; use `source venv/bin/activate` on macOS/Linux
   pip install -r requirements.txt
   cp .env.example .env
   ```

   In `python-service/.env`, set:

   ```ini
   INTERNAL_KEY=<same value as in the Laravel .env>
   STORAGE_PATH=../storage/app/data-processing
   ```

   Then start it on port 8001 (`php artisan serve` already uses 8000):

   ```sh
   uvicorn app.main:app --reload --port 8001
   ```

3. **In the Laravel `.env`**, set `INTERNAL_KEY` to the same value and
   `PYTHON_SERVICE_URL=http://localhost:8001`.

4. **Run the app, queue worker and Vite together:**

   ```sh
   composer dev
   ```

   Open <http://localhost:8000>.

### First login

The seeder creates a superadmin: `admin@datasciencetool.local` / `password`.
**Change this password straight away**, and never run the seeder in production as-is.

New accounts registered through the sign-up page have no role until a superadmin
assigns one.

## Configuration

| Variable | Where | Purpose |
| --- | --- | --- |
| `INTERNAL_KEY` | Both `.env` files | Shared secret between Laravel and Python. Must match on both sides and be at least 16 random characters; the Python service refuses to start otherwise. |
| `PYTHON_SERVICE_URL` | Laravel | Where Laravel reaches the Python service. |
| `PYTHON_SERVICE_TIMEOUT` | Laravel | Seconds to wait for one Python response (default 240). Queue jobs time out 30 seconds later. |
| `DB_QUEUE_RETRY_AFTER` / `REDIS_QUEUE_RETRY_AFTER` | Laravel | Must stay above `PYTHON_SERVICE_TIMEOUT` + 30 (default 300), or a long job can run twice. |
| `STORAGE_PATH` | Python | The same folder Laravel's `data-processing` disk uses (`storage/app/data-processing`). |

## Schema contracts

A contract describes the columns a team expects. Superadmins create them under
**Contracts**, as JSON:

```json
{
  "columns": [
    { "name": "id", "type": "integer", "required": true },
    { "name": "email", "type": "string", "required": true, "nullable": false },
    { "name": "created_at", "type": "datetime", "required": false }
  ]
}
```

Validation reports a missing column (unless `"required": false`), null values in a
`"nullable": false` column, and values that don't match the type. Integer, float and
boolean types are type-checked.

## Running the tests

```sh
php artisan test                          # Laravel (Pest)
vendor/bin/pint --test                    # code style
cd python-service && python -m pytest -q  # Python service
```

The Python tests include contract tests that send requests shaped exactly as Laravel
sends them, so a field renamed on only one side fails the build. GitHub Actions runs
all of the above, plus a frontend build, on every pull request and on every push to `main`.

## Project layout

```text
app/
  Enums/          Run statuses and step types (and which actions each status allows)
  Http/           Controllers for runs, clean, validate, export, contracts, deliveries, admin
  Jobs/           Queued steps; RunStepJob holds the shared retry and failure handling
  Models/         Run, RunStep, SchemaContract, Delivery, User
  Policies/       Run, contract and delivery authorisation
  Services/       PythonServiceClient: the only code that talks to Python
resources/js/     Vue pages (Inertia)
python-service/
  app/routers/    One FastAPI router per endpoint
  app/services/   Profiling, cleaning, validation, export, extraction
  tests/          pytest: security and Laravel contract tests
project-spec.md   The full specification and build plan
```

## Known limitations

- **Supported formats for processing:** CSV, Excel (`.xlsx`, `.xls`) and Parquet.
  Uploads also accept JSON, TXT, DOC and DOCX, but they can't be profiled yet.
- **PDF extraction** exists in the Python service (`/extract`), but nothing in the
  web app calls it yet.
- **Deployment:** CI runs the tests, but doesn't yet build or publish Docker images.
