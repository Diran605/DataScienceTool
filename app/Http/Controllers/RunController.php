<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Http\Requests\StoreRunRequest;
use App\Jobs\ProfileRunJob;
use App\Models\Run;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RunController extends Controller
{
    /**
     * Display a listing of runs.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $runs = Run::query()
            ->when(! $user->hasRole('superadmin'), function ($query) use ($user) {
                $query->where('created_by', $user->id);
            })
            ->with('creator:id,name')
            ->withCount('steps')
            ->latest()
            ->paginate(15);

        return Inertia::render('Runs/Index', [
            'runs' => $runs,
        ]);
    }

    /**
     * Show the form for creating a new run (file upload).
     */
    public function create(): Response
    {
        Gate::authorize('create', Run::class);

        return Inertia::render('Runs/Create');
    }

    /**
     * Store a new run — upload file and dispatch profiling job.
     */
    public function store(StoreRunRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $disk = Storage::disk('data-processing');

        $filePath = $disk->putFileAs(
            'uploads',
            $file,
            time().'_'.$file->getClientOriginalName()
        );

        $run = Run::create([
            'source_file_path' => $filePath,
            'source_file_type' => $file->getClientOriginalExtension(),
            'created_by' => $request->user()->id,
        ]);

        ProfileRunJob::dispatch($run);

        return redirect()->route('runs.show', $run)
            ->with('success', 'File uploaded. Profiling has started.');
    }

    /**
     * Display a single run with its steps.
     */
    public function show(Run $run): Response
    {
        Gate::authorize('view', $run);

        $run->load(['creator:id,name', 'steps' => function ($query) {
            $query->latest();
        }]);

        return Inertia::render('Runs/Show', [
            'run' => $run,
            'canProcess' => Gate::allows('process', $run),
        ]);
    }

    /**
     * Reprofile the run.
     */
    public function reprofile(Run $run): RedirectResponse
    {
        Gate::authorize('process', $run);

        abort_unless(
            in_array($run->status, RunStatus::reprofilable(), true),
            422,
            'Run is still being processed.'
        );

        abort_unless($run->claim(RunStatus::reprofilable(), RunStatus::Profiling), 409, 'This run is already being processed.');

        ProfileRunJob::dispatch($run);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Reprofiling has started.');
    }

    /**
     * Remove the specified run.
     */
    public function destroy(Run $run): RedirectResponse
    {
        Gate::authorize('delete', $run);

        // Clean up the original upload and every intermediate cleaned file. Export
        // outputs are left alone because deliveries may still reference them.
        $filePaths = $run->steps()
            ->where('step_type', StepType::Clean)
            ->whereNotNull('output_file_path')
            ->pluck('output_file_path')
            ->push($run->source_file_path, $run->current_file_path)
            ->filter()
            ->unique();

        $disk = Storage::disk('data-processing');
        foreach ($filePaths as $filePath) {
            if ($disk->exists($filePath)) {
                $disk->delete($filePath);
            }
        }

        $run->delete();

        return redirect()->route('runs.index')
            ->with('success', 'Run deleted.');
    }
}
