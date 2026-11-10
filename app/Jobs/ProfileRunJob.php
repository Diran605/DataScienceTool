<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Models\RunStep;
use App\Services\PythonServiceClient;
use Throwable;

class ProfileRunJob extends RunStepJob
{
    /**
     * Execute the job — call Python /profile and store the quality report.
     */
    public function handle(PythonServiceClient $pythonService): void
    {
        $this->run->update(['status' => RunStatus::Profiling]);

        $filePathToProfile = $this->run->workingFilePath();

        $step = RunStep::create([
            'run_id' => $this->run->id,
            'step_type' => StepType::Profile,
            'input_file_path' => $filePathToProfile,
            'status' => 'running',
        ]);

        try {
            $report = $pythonService->profile($filePathToProfile);

            $step->update([
                'result' => $report,
                'status' => 'completed',
            ]);

            $this->run->update([
                'status' => $this->run->current_file_path ? RunStatus::Cleaned : RunStatus::Profiled,
            ]);
        } catch (Throwable $e) {
            $this->handleStepFailure($step, $e);
        }
    }
}
