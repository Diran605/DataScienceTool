<?php

namespace App\Http\Controllers;

use App\Jobs\ExportRunJob;
use App\Models\Run;
use App\Models\SchemaContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExportController extends Controller
{
    /**
     * Show export options for a validated run.
     */
    public function create(Run $run): Response
    {
        Gate::authorize('view', $run);

        $run->load('steps');

        return Inertia::render('Runs/Export', [
            'run' => $run,
        ]);
    }

    /**
     * Dispatch the export job.
     */
    public function store(Request $request, Run $run): RedirectResponse
    {
        Gate::authorize('view', $run);

        abort_unless(
            in_array($run->status, [\App\Enums\RunStatus::Profiled]),
            422,
            'Run must be validated before export.'
        );

        $validated = $request->validate([
            'schema_contract_id' => ['required', 'exists:schema_contracts,id'],
            'format' => ['required', 'string', 'in:csv,json,parquet'],
        ]);

        $contract = SchemaContract::findOrFail($validated['schema_contract_id']);

        ExportRunJob::dispatch($run, $contract, $validated['format']);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Export has started.');
    }
}
