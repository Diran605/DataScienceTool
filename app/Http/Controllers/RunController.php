<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRunRequest;
use App\Jobs\ProfileRunJob;
use App\Models\Run;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class RunController extends Controller
{
    /**
     * Display a listing of runs.
     */
    public function index(): Response
    {
        $user = auth()->user();

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
        \Illuminate\Support\Facades\Gate::authorize('create', Run::class);

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
        \Illuminate\Support\Facades\Gate::authorize('view', $run);

        $run->load(['creator:id,name', 'steps' => function ($query) {
            $query->latest();
        }]);

        return Inertia::render('Runs/Show', [
            'run' => $run,
        ]);
    }

    /**
     * Reprofile the run.
     */
    public function reprofile(Run $run): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $run);

        ProfileRunJob::dispatch($run);

        return redirect()->route('runs.show', $run)
            ->with('success', 'Reprofiling has started.');
    }

    /**
     * Remove the specified run.
     */
    public function destroy(Run $run): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('delete', $run);

        // Clean up the uploaded file
        $disk = Storage::disk('data-processing');
        if ($disk->exists($run->source_file_path)) {
            $disk->delete($run->source_file_path);
        }

        $run->delete();

        return redirect()->route('runs.index')
            ->with('success', 'Run deleted.');
    }
}
