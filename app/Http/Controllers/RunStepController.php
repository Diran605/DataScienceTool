<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\RunStep;
use Inertia\Inertia;
use Inertia\Response;

class RunStepController extends Controller
{
    /**
     * Display a specific step's details (config + result).
     */
    public function show(Run $run, RunStep $step): Response
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $run);

        abort_unless($step->run_id === $run->id, 404);

        return Inertia::render('Runs/Steps/Show', [
            'run' => $run->only('id', 'status', 'source_file_type'),
            'step' => $step,
        ]);
    }
}
