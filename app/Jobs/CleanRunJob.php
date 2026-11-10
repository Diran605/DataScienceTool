<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\PythonServiceClient;
use App\Services\PythonServiceException;
use Throwable;

class CleanRunJob extends RunStepJob
{
    /**
     * @param  array<int, array<string, mixed>>  $steps  Ordered cleaning step configs
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        Run $run,
        public array $steps,
        public array $options = [],
    ) {
        parent::__construct($run);
    }

    /**
     * Execute the job — call Python /clean and store the diff.
     */
    public function handle(PythonServiceClient $pythonService): void
    {
        $this->run->update(['status' => RunStatus::Cleaning]);

        // The step list is always the full pipeline, applied to the original upload.
        // Re-cleaning replaces the previous result rather than stacking on top of it,
        // which keeps every run replayable and makes undo/redo of steps possible.
        $step = RunStep::create([
            'run_id' => $this->run->id,
            'step_type' => StepType::Clean,
            'input_file_path' => $this->run->source_file_path,
            'config' => ['steps' => $this->steps, 'options' => $this->options],
            'status' => 'running',
        ]);

        try {
            $result = $pythonService->clean($this->run->source_file_path, $this->steps, $this->options);

            $cleanedFilePath = $result['cleaned_file_path'] ?? null;

            if (empty($cleanedFilePath)) {
                throw PythonServiceException::invalidResponse('/clean', 'no cleaned file path was returned.');
            }

            $step->update([
                'output_file_path' => $cleanedFilePath,
                'result' => $result,
                'status' => 'completed',
            ]);

            $this->run->update(['current_file_path' => $cleanedFilePath]);

            // Reprofile the cleaned dataset so the user can compare before/after.
            ProfileRunJob::dispatch($this->run);
        } catch (Throwable $e) {
            $this->handleStepFailure($step, $e);
        }
    }
}
