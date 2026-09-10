<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Jobs\CleanRunJob;
use App\Models\Run;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CleanController extends Controller
{
    /**
     * Show the cleaning panel for a profiled run.
     */
    public function edit(Run $run): Response
    {
        Gate::authorize('view', $run);

        abort_unless($run->status === RunStatus::Profiled, 422, 'Run must be profiled before cleaning.');

        $run->load(['steps' => fn ($q) => $q->latest()]);

        $profileStep = $run->steps->firstWhere('step_type', StepType::Profile);
        $lastCleanStep = $run->steps->firstWhere('step_type', StepType::Clean);

        return Inertia::render('Runs/Clean', [
            'run' => $run,
            'profileResult' => $profileStep?->result,
            'lastCleanResult' => $lastCleanStep?->result,
            'lastCleanConfig' => $lastCleanStep?->config,
        ]);
    }

    /**
     * Apply cleaning steps to the run's data.
     */
    public function update(Request $request, Run $run): RedirectResponse
    {
        Gate::authorize('view', $run);

        abort_unless($run->status === RunStatus::Profiled, 422, 'Run must be profiled before cleaning.');

        $validated = $request->validate([
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.action' => ['required', 'string'],
            'steps.*.columns' => ['sometimes', 'array'],
            'steps.*.strategy' => ['sometimes', 'string'],
            'steps.*.value' => ['sometimes'],
            'steps.*.pattern' => ['sometimes', 'string'],
            'steps.*.mapping' => ['sometimes', 'array'],
        ]);

        CleanRunJob::dispatch($run, $validated['steps']);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Cleaning has started.');
    }
}
