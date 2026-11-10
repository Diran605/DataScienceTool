<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Models\Delivery;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\SchemaContract;
use App\Services\PythonServiceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportRunJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public Run $run,
        public ?SchemaContract $contract,
        public string $format,
        public ?string $overrideReason = null,
    ) {}

    /**
     * Execute the job — call Python /export and create delivery record.
     */
    public function handle(PythonServiceClient $pythonService): void
    {
        $this->run->update(['status' => RunStatus::Exporting]);

        $filePathToExport = $this->run->workingFilePath();

        $step = RunStep::create([
            'run_id' => $this->run->id,
            'step_type' => StepType::Export,
            'input_file_path' => $filePathToExport,
            'config' => [
                'schema_contract_id' => $this->contract?->id,
                'format' => $this->format,
                // Set when exporting data that has not passed validation (audit trail).
                'override_reason' => $this->overrideReason,
            ],
            'status' => 'running',
        ]);

        try {
            $result = $pythonService->export(
                $filePathToExport,
                $this->format,
                $this->contract?->schema_definition ?? []
            );

            $step->update([
                'output_file_path' => $result['export_url'] ?? null,
                'result' => $result,
                'status' => 'completed',
            ]);

            // Create the delivery record
            Delivery::create([
                'run_id' => $this->run->id,
                'schema_contract_id' => $this->contract?->id,
                'output_path' => $result['export_url'] ?? '',
                'delivered_by' => $this->run->created_by,
                'delivered_at' => now(),
            ]);

            $this->run->update(['status' => RunStatus::Delivered]);
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
