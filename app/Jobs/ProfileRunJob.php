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

class ProfileRunJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    public function __construct(
        public Run $run,
    ) {}

    /**
     * Execute the job — call Python /profile and store the quality report.
     */
    public function handle(PythonServiceClient $pythonService): void
    {
        $this->run->update(['status' => RunStatus::Profiling]);

        $step = RunStep::create([
            'run_id' => $this->run->id,
            'step_type' => StepType::Profile,
            'status' => 'running',
        ]);

        try {
            $filePathToProfile = $this->run->cleaned_file_path ?? $this->run->source_file_path;
            $report = $pythonService->profile($filePathToProfile);

            $step->update([
                'result' => $report,
                'status' => 'completed',
            ]);

            $this->run->update(['status' => RunStatus::Profiled]);
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
