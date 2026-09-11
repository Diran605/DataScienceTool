<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\PythonServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class CleanRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  array<int, array<string, mixed>>  $steps  Ordered cleaning step configs
     */
    public function __construct(
        public Run $run,
        public array $steps,
        public array $options = [],
    ) {}

    /**
     * Execute the job — call Python /clean and store the diff.
     */
    public function handle(PythonServiceClient $pythonService): void
    {
        $this->run->update(['status' => RunStatus::Cleaning]);

        $step = RunStep::create([
            'run_id' => $this->run->id,
            'step_type' => StepType::Clean,
            'config' => ['steps' => $this->steps, 'options' => $this->options],
            'status' => 'running',
        ]);

        try {
            $result = $pythonService->clean($this->run->source_file_path, $this->steps, $this->options);

            $step->update([
                'result' => $result,
                'status' => 'completed',
            ]);

            // Update the Run with the newly generated cleaned file path
            if (isset($result['cleaned_file_path']) && !empty($result['cleaned_file_path'])) {
                $this->run->update([
                    'source_file_path' => $result['cleaned_file_path']
                ]);
            }

            // Immediately reprofile the newly cleaned dataset so the user can see the comparison
            \App\Jobs\ProfileRunJob::dispatch($this->run);
        } catch (Throwable $e) {
            $step->update([
                'result' => ['error' => $e->getMessage()],
                'status' => 'failed',
            ]);

            $this->run->update(['status' => RunStatus::Failed]);

            throw $e;
        }
    }
}
