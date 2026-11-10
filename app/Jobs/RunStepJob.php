<?php

namespace App\Jobs;

use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\RunStep;
use App\Services\PythonServiceClient;
use App\Services\PythonServiceException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Base class for jobs that run one step of a run through the Python service.
 *
 * - Transient failures (service unreachable or 502-504) are retried with backoff.
 * - Permanent failures (bad input, service errors) fail the job immediately.
 * - The run is only marked failed once the job has finally failed, not on every
 *   attempt, so a run that recovers on retry never shows as failed.
 */
abstract class RunStepJob implements ShouldQueue
{
    use Queueable;

    /** Attempts in total, including the first. */
    public int $tries = 3;

    /** A timed-out step would most likely time out again on the same file. */
    public bool $failOnTimeout = true;

    /**
     * Seconds before the worker kills the job. Leaves headroom over the HTTP timeout
     * for reading/writing the step and run. config/queue.php's retry_after must
     * stay above this, or a still-running job is handed to a second worker.
     */
    public int $timeout;

    public function __construct(public Run $run)
    {
        $this->timeout = PythonServiceClient::timeoutSeconds() + 30;
    }

    /**
     * Seconds to wait before each retry.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    /**
     * Record the failure on the step, then either fail the job for good or rethrow
     * so the queue retries it.
     *
     * @throws Throwable when the failure is worth retrying
     */
    protected function handleStepFailure(RunStep $step, Throwable $e): void
    {
        $step->update([
            'result' => ['error' => $e->getMessage(), 'attempt' => $this->attempts()],
            'status' => 'failed',
        ]);

        if ($e instanceof PythonServiceException && ! $e->isRetryable()) {
            $this->fail($e);

            return;
        }

        throw $e;
    }

    /**
     * Called by the queue once the job has failed for good.
     */
    public function failed(?Throwable $exception): void
    {
        // A timed-out or killed job never reaches its catch block, so its step
        // would otherwise stay "running" forever.
        $this->run->steps()
            ->where('status', 'running')
            ->each(fn (RunStep $step) => $step->update([
                'status' => 'failed',
                'result' => ['error' => $exception?->getMessage() ?? 'The job stopped unexpectedly.'],
            ]));

        $this->run->update(['status' => RunStatus::Failed]);
    }
}
