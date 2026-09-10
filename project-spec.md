# Project: Data Cleaning & Migration Delivery System

## 1. Purpose

Build an internal web application used by a single data scientist/engineer (the
"superadmin") to ingest messy data (structured, semi-structured, and
unstructured), clean and standardize it interactively through a UI, validate
it against per-team schema contracts, and export/deliver it in the format
each software development team requires (flat files or JSON matching an API
contract). The system will later support additional users (operators/viewers)
scoped to specific team contracts.

This is an internal tool, not a public product. Prioritize correctness,
traceability (lineage/audit), and a clean interactive review experience over
raw scale — but design the data layer so it can later handle large files
without a rewrite.

---

## 2. Architecture

Two services, containerized, communicating over an internal network:

```
┌───────────────────────┐        HTTP (internal, keyed)        ┌───────────────────────────┐
│  Laravel + Vue (Inertia)│ ───────────────────────────────────► │  Python service (FastAPI) │
│  - UI, auth, RBAC       │ ◄─────────────────────────────────── │  - profiling               │
│  - orchestration        │        JSON responses                │  - extraction (PDF/text)   │
│  - schema contracts      │                                      │  - cleaning                │
│  - runs & delivery log   │                                      │  - schema validation       │
│  - queue jobs            │                                      │  - export rendering        │
└───────────┬─────────────┘                                      └───────────────┬───────────┘
            │                                                                     │
            ▼                                                                     ▼
     MySQL/Postgres (app data)                                    Shared file storage (uploads,
     Redis (queues)                                                intermediate, exports)
```

Design principle: **Laravel owns state and workflow. Python is stateless** —
it receives a file/config, does the processing, and returns a result. Laravel
never re-implements data science logic; Python never stores durable app
state (users, contracts, run history).

---

## 3. Tech stack

- **Backend/UI**: Laravel (latest LTS), Vue 3 via Inertia.js, Tailwind for styling
- **Auth/RBAC**: Laravel Breeze/Fortify for auth + `spatie/laravel-permission` for roles/permissions
- **Queue**: Laravel Queues backed by Redis (for long-running profile/clean/export jobs)
- **Database**: MySQL or Postgres (Postgres preferred if available)
- **Processing service**: Python 3.11+, FastAPI, Uvicorn
  - `pandas`, `polars` (for larger files), `ydata-profiling` or `great_expectations` (profiling),
    `unstructured` / `pdfplumber` (PDF/doc/text extraction), `pydantic` (schema contract validation)
- **File storage**: Laravel filesystem abstraction (`Storage::disk()`), local disk in dev, S3-compatible
  in production (do not hardcode local paths anywhere — always go through the disk abstraction)
- **Containerization**: Docker + Docker Compose (nginx/php-fpm for Laravel, uvicorn for Python, MySQL/Postgres, Redis)

---

## 4. Core workflow (implement in this order)

1. **Upload** — Vue file upload form → Laravel controller stores the file via `Storage::disk()`,
   creates a `runs` row (status: `pending`), dispatches a queued job.
2. **Profile** — Job calls Python `POST /profile` with the file location/id. Python detects file
   type, routes to the right parser (tabular vs PDF/doc/free text extraction first), runs quality
   checks (nulls, dupes, type mismatches, outliers, encoding issues), returns a JSON quality report.
   Laravel stores the report on the `run_steps` row and updates run status to `profiled`.
3. **Review** — Vue renders the quality report (tables/summary stats). User (you) reviews it.
4. **Clean** — User selects cleaning actions from a predefined, composable set (drop/fill nulls,
   dedupe, normalize dates/text, rename/merge columns, regex extraction for free text). Laravel
   sends the chosen steps as a JSON config to Python `POST /clean`. Python applies them, returns
   cleaned data + a diff/preview. Support iterative re-cleaning (undo/redo the step list).
5. **Schema mapping & validation** — User selects a target `schema_contracts` row (the dev team's
   contract). Laravel sends cleaned data + the contract's schema definition to Python
   `POST /validate`. Python validates via Pydantic/JSON Schema, returns pass/fail + violation list.
   Do not allow export on failed validation without an explicit override + logged reason.
6. **Export** — Laravel calls Python `POST /export` with the target format (csv, parquet,
   json-per-contract, or db-staging-write). Python renders the output file(s).
7. **Deliver** — Laravel creates a `deliveries` row (run, contract, timestamp, output path),
   surfaces a download link in Vue, and (optionally) writes to a staging DB table if that's the
   contract's target.

Every step's input/output should be persisted (at minimum as JSON blobs on `run_steps`) so any run
can be inspected or replayed later — this is the audit/lineage requirement.

---

## 5. Database schema (Laravel migrations)

```
users
  id, name, email, password, timestamps

roles / permissions / model_has_roles / model_has_permissions   -- from spatie/laravel-permission

schema_contracts
  id, dev_team_name, format (enum: csv, json, db), schema_definition (json),
  created_by (FK users), timestamps

contract_user                          -- per-contract/team scoping
  id, user_id (FK), schema_contract_id (FK), can_run (bool), can_view_deliveries (bool), timestamps

runs
  id, source_file_path, source_file_type, status
    (enum: pending, profiling, profiled, cleaning, validating, exporting, delivered, failed),
  created_by (FK users), timestamps

run_steps
  id, run_id (FK), step_type (enum: profile, clean, validate, export),
  config (json, nullable), result (json, nullable), status, timestamps

deliveries
  id, run_id (FK), schema_contract_id (FK), output_path, delivered_by (FK users), delivered_at
```

Roles to seed: `superadmin`, `operator`, `viewer`.
Permissions to seed: `runs.create`, `runs.view`, `runs.delete`, `contracts.manage`,
`users.manage`, `deliveries.view`.

Authorization pattern:
- Role/permission check (Spatie) gates *what kind of action* a user can take.
- A `SchemaContractPolicy` gates *which specific contract* a user can act on, via the
  `contract_user` pivot. Superadmin bypasses contract scoping entirely.
- Vue only ever displays contracts/actions the backend has already scoped to the user —
  never rely on frontend hiding as the actual enforcement.

---

## 6. Python service (FastAPI) — endpoints

All endpoints require the `X-Internal-Key` header (shared secret from env) or, if you implement
the stronger option, HMAC-SHA256 signature verification (`X-Signature` header over the raw request
body). Return `401` on failure. This service should not be reachable outside the internal Docker
network in any environment.

- `POST /profile` — input: file reference. output: quality report JSON (nulls, dupes, type issues,
  outliers per column; extraction summary if source was PDF/doc/text).
- `POST /extract` — input: file reference (PDF/doc/free text). output: structured records extracted
  from the unstructured source, with confidence/provenance metadata.
- `POST /clean` — input: file reference or in-memory dataset ref + ordered list of cleaning step
  configs. output: cleaned dataset (or ref to it) + before/after diff summary.
- `POST /validate` — input: cleaned dataset ref + schema contract definition. output: pass/fail +
  list of field-level violations.
- `POST /export` — input: cleaned dataset ref + target format + (for JSON) the contract's schema.
  output: file reference(s) ready for Laravel to expose as a download / write to a staging table.

Keep the service stateless: it should operate on file references Laravel passes in (from shared
storage) rather than holding session state between calls.

---

## 7. Auth between services

- Shared secret stored in `.env` on both sides (`INTERNAL_KEY`), never committed to version control.
- Laravel sends it as `X-Internal-Key` header on every request to the Python service.
- Python verifies it via a FastAPI dependency before processing any request.
- If/when the callback direction is needed (Python notifies Laravel a background job is done via a
  webhook), protect that Laravel route with the same shared secret check — not normal session auth —
  and make sure it's not reachable by a logged-in browser session.
- Stronger option (implement if/when this becomes reachable beyond localhost, or once more than the
  superadmin is driving it): HMAC-sign the request body instead of a static key check.

---

## 8. Deployment

- **Local dev now**: `docker-compose.yml` with services: `laravel` (nginx+php-fpm), `python-service`
  (uvicorn), `mysql` or `postgres`, `redis`. Shared volume for uploads/exports.
- **Near-term production**: same containers, deployed to a single VPS or a managed container
  platform (Railway/Render/Fly.io) — pick based on convenience, not a hard requirement here.
- **Future**: AWS (ECS/Fargate or EC2 for containers, RDS for the database, S3 for file storage).
  Build toward this now by:
  - Using `Storage::disk()` in Laravel everywhere (never raw local file paths) so switching to S3
    is a config change.
  - Keeping all config in environment variables, no hardcoded hosts/secrets.
  - Treating the Docker images as the deployable artifact, so the same images can later be pushed to
    ECS without a rebuild-from-scratch.
- HTTPS via Let's Encrypt/Caddy (or platform-managed certs) once deployed beyond localhost.
- Basic CI (GitHub Actions is sufficient): run tests, build images, push to a registry on merge to
  main.

---

## 9. Build order for the agent

1. Scaffold Laravel project with Breeze/Fortify auth + Inertia + Vue 3 + Tailwind.
2. Install and configure `spatie/laravel-permission`; write migrations/seeders for roles,
   permissions, and the `contract_user` pivot.
3. Write migrations for `schema_contracts`, `runs`, `run_steps`, `deliveries`.
4. Scaffold the FastAPI service with the five endpoints stubbed (return mock data first), plus the
   `X-Internal-Key` auth dependency.
5. Wire Laravel → Python calls for `/profile` first, end-to-end (upload → job → profile → stored
   report → Vue display), before building out `/clean`, `/validate`, `/export`.
6. Implement `/clean`, `/validate`, `/export` in Python with real logic (pandas-based to start).
7. Build the Vue screens: upload, quality report review, interactive cleaning panel, schema
   contract picker, export/delivery confirmation, and a basic admin screen (superadmin only) for
   managing users, roles, and contract scoping.
8. Write `docker-compose.yml` tying all services together with a shared volume and internal network.
9. Add basic tests: Laravel feature tests for the run lifecycle and authorization policies; Python
   unit tests for each processing endpoint with sample messy data fixtures.

---

## 10. Non-goals for this phase

- No AWS-specific infrastructure work yet (design for it, don't build it).
- No multi-tenant support beyond per-contract scoping described above.
- No real-time collaboration (single operator per run at a time is fine).
- No UI polish beyond functional clarity — this is an internal tool.
