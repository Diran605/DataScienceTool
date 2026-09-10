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
    public function create(Run $run): Response
    {
        Gate::authorize('view', $run);

        abort_unless(
            in_array($run->status, [RunStatus::Profiled, RunStatus::Cleaning]),
            422,
            'Run must be profiled or cleaned before validation.'
        );

        $user = auth()->user();

        $contracts = SchemaContract::query()
            ->when(! $user->hasRole('superadmin'), function ($query) use ($user) {
                $query->whereHas('users', fn ($q) => $q->where('user_id', $user->id));
            })
            ->get(['id', 'dev_team_name', 'format']);

        return Inertia::render('Runs/Validate', [
            'run' => $run->load('steps'),
            'contracts' => $contracts,
        ]);
    }

    /**
     * Dispatch validation against the selected contract.
     */
    public function store(Request $request, Run $run): RedirectResponse
    {
        Gate::authorize('view', $run);

        abort_unless(
            in_array($run->status, [RunStatus::Profiled, RunStatus::Cleaning]),
            422,
            'Run must be profiled or cleaned before validation.'
        );

        $validated = $request->validate([
            'schema_contract_id' => ['required', 'exists:schema_contracts,id'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $contract = SchemaContract::findOrFail($validated['schema_contract_id']);

        ValidateRunJob::dispatch($run, $contract, $validated['override_reason'] ?? null);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Validation has started.');
    }
}
