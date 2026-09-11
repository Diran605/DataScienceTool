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

        abort_unless(in_array($run->status->value, ['profiled', 'cleaned', 'validating', 'exporting', 'delivered']), 422, 'Run must be profiled before cleaning.');

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

        abort_unless(in_array($run->status->value, ['profiled', 'cleaned', 'validating', 'exporting', 'delivered']), 422, 'Run must be profiled before cleaning.');

        $validated = $request->validate([
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.action' => ['required', 'string'],
            'steps.*.columns' => ['sometimes', 'array', 'nullable'],
            'steps.*.strategy' => ['sometimes', 'string', 'nullable'],
            'steps.*.value' => ['sometimes', 'nullable'],
            'steps.*.pattern' => ['sometimes', 'string', 'nullable'],
            'steps.*.mapping' => ['sometimes', 'array', 'nullable'],
            'options' => ['sometimes', 'array', 'nullable'],
            'options.preview_only' => ['sometimes', 'boolean', 'nullable'],
        ]);

        if (!empty($validated['options']['preview_only'])) {
            try {
                $client = app(\App\Services\PythonServiceClient::class);
                $result = $client->clean($run->source_file_path, $validated['steps'], ['preview_only' => true]);
                
                $run->steps()->create([
                    'step_type' => StepType::Clean,
                    'status' => 'completed',
                    'config' => ['steps' => $validated['steps']],
                    'result' => $result,
                ]);

                return redirect()->back()->with('success', 'Preview generated successfully.');
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Preview failed: ' . $e->getMessage());
            }
        }

        CleanRunJob::dispatch($run, $validated['steps'], $validated['options'] ?? []);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Cleaning has started. The results will appear here once complete.');
    }
}
