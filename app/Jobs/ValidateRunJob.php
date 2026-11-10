<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\SchemaContract;
use App\Services\PythonServiceClient;
use Throwable;

class ValidateRunJob extends RunStepJob
{
    public function __construct(
        Run $run,
        public SchemaContract $contract,
    ) {
        parent::__construct($run);
    }

    /**
     * Execute the job — call Python /validate and store pass/fail.
     */
    public function handle(PythonServiceClient $pythonService): void
    {
        $this->run->update(['status' => RunStatus::Validating]);

        $filePathToValidate = $this->run->workingFilePath();

        $step = RunStep::create([
            'run_id' => $this->run->id,
            'step_type' => StepType::Validate,
            'input_file_path' => $filePathToValidate,
            'config' => [
                'schema_contract_id' => $this->contract->id,
            ],
            'status' => 'running',
        ]);

        try {
            $result = $pythonService->validate(
                $filePathToValidate,
                $this->contract->schema_definition
            );

            $step->update([
                'result' => $result,
                'status' => 'completed',
            ]);

            $this->run->update([
                'status' => ($result['is_valid'] ?? false) ? RunStatus::Validated : RunStatus::ValidationFailed,
            ]);
        } catch (Throwable $e) {
            $this->handleStepFailure($step, $e);
        }
    }
}
