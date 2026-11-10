<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Models\Delivery;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\SchemaContract;
use App\Services\PythonServiceClient;
use App\Services\PythonServiceException;
use Throwable;

class ExportRunJob extends RunStepJob
{
    public function __construct(
        Run $run,
        public ?SchemaContract $contract,
        public string $format,
        public ?string $overrideReason = null,
    ) {
        parent::__construct($run);
    }

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

            $exportPath = $result['export_url'] ?? null;

            if (empty($exportPath)) {
                throw PythonServiceException::invalidResponse('/export', 'no export file path was returned.');
            }

            $step->update([
                'output_file_path' => $exportPath,
                'result' => $result,
                'status' => 'completed',
            ]);

            Delivery::create([
                'run_id' => $this->run->id,
                'schema_contract_id' => $this->contract?->id,
                'output_path' => $exportPath,
                'delivered_by' => $this->run->created_by,
                'delivered_at' => now(),
            ]);

            $this->run->update(['status' => RunStatus::Delivered]);
        } catch (Throwable $e) {
            $this->handleStepFailure($step, $e);
        }
    }
}
