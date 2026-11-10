<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Jobs\ValidateRunJob;
use App\Models\Run;
use App\Models\SchemaContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ValidateController extends Controller
{
    /**
     * Show the validation form — pick a contract and validate.
     */
    public function create(Request $request, Run $run): Response
    {
        Gate::authorize('process', $run);

        abort_unless($run->status->canValidate(), 422, 'Run must be profiled or cleaned before validation.');

        return Inertia::render('Runs/Validate', [
            'run' => $run->load('steps'),
            'contracts' => SchemaContract::query()
                ->runnableBy($request->user())
                ->get(['id', 'dev_team_name', 'format']),
        ]);
    }

    /**
     * Dispatch validation against the selected contract.
     */
    public function store(Request $request, Run $run): RedirectResponse
    {
        Gate::authorize('process', $run);

        abort_unless($run->status->canValidate(), 422, 'Run must be profiled or cleaned before validation.');

        $validated = $request->validate([
            'schema_contract_id' => ['required', 'exists:schema_contracts,id'],
        ]);

        $contract = SchemaContract::findOrFail($validated['schema_contract_id']);

        Gate::authorize('run', $contract);

        abort_unless($run->claim(RunStatus::settled(), RunStatus::Validating), 409, 'This run is already being processed.');

        ValidateRunJob::dispatch($run, $contract);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Validation has started.');
    }
}
