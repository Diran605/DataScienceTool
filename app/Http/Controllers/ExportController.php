<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Jobs\ExportRunJob;
use App\Models\Run;
use App\Models\SchemaContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExportController extends Controller
{
    /**
     * Show export options for a run.
     */
    public function create(Request $request, Run $run): Response
    {
        Gate::authorize('process', $run);

        abort_unless($run->status->canExport(), 422, 'Run must be profiled before export.');

        $run->load('steps');

        return Inertia::render('Runs/Export', [
            'run' => $run,
            'contracts' => SchemaContract::query()
                ->runnableBy($request->user())
                ->get(['id', 'dev_team_name', 'format']),
            'hasPassedValidation' => $run->hasPassedValidation(),
        ]);
    }

    /**
     * Dispatch the export job. Data that has not passed validation (against the
     * chosen contract, if any) can only be exported with a logged override reason.
     */
    public function store(Request $request, Run $run): RedirectResponse
    {
        Gate::authorize('process', $run);

        abort_unless($run->status->canExport(), 422, 'Run must be profiled before export.');

        $validated = $request->validate([
            'schema_contract_id' => ['nullable', 'exists:schema_contracts,id'],
            'format' => ['required', 'string', 'in:csv,json,parquet'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $contract = null;
        if (! empty($validated['schema_contract_id'])) {
            $contract = SchemaContract::findOrFail($validated['schema_contract_id']);
            Gate::authorize('run', $contract);
        }

        $hasPassedValidation = $run->hasPassedValidation($contract);

        // Only record an override when one was actually needed.
        $overrideReason = $hasPassedValidation ? null : (trim($validated['override_reason'] ?? '') ?: null);

        if (! $hasPassedValidation && $overrideReason === null) {
            throw ValidationException::withMessages([
                'override_reason' => $contract
                    ? 'This data has not passed validation against the selected contract. Provide an override reason to export anyway.'
                    : 'This data has not passed validation. Provide an override reason to export anyway.',
            ]);
        }

        abort_unless($run->claim(RunStatus::settled(), RunStatus::Exporting), 409, 'This run is already being processed.');

        ExportRunJob::dispatch($run, $contract, $validated['format'], $overrideReason);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Export has started.');
    }
}
