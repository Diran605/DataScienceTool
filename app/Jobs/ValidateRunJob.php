<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\SchemaContract;
use App\Services\PythonServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ValidateRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public Run $run,
        public SchemaContract $contract,
        public ?string $overrideReason = null,
    ) {}

    /**
     * Execute the job — call Python /validate and store pass/fail.
     */
    public function handle(PythonServiceClient $pythonService): void
    {
        $this->run->update(['status' => RunStatus::Validating]);

        $step = RunStep::create([
            'run_id' => $this->run->id,
            'step_type' => StepType::Validate,
            'config' => [
                'schema_contract_id' => $this->contract->id,
                'override_reason' => $this->overrideReason,
            ],
            'status' => 'running',
        ]);

        try {
            $result = $pythonService->validate(
                $this->run->source_file_path,
                $this->contract->schema_definition
            );

            $step->update([
                'result' => $result,
                'status' => 'completed',
            ]);

            // Stay at validating status — user needs to review results
            // and either proceed to export or fix issues
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
