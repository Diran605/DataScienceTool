<?php

use App\Enums\RunStatus;
use App\Enums\StepType;
use App\Jobs\CleanRunJob;
use App\Jobs\ExportRunJob;
use App\Jobs\ProfileRunJob;
use App\Jobs\ValidateRunJob;
use App\Models\Run;
use App\Models\RunStep;
use App\Models\SchemaContract;
use App\Services\PythonServiceClient;
use App\Services\PythonServiceException;

/**
 * A Python client mock whose profile() call throws the given exception.
 */
function pythonClientThrowingOnProfile(Throwable $exception): PythonServiceClient
{
    $python = Mockery::mock(PythonServiceClient::class);
    $python->shouldReceive('profile')->andThrow($exception);

    return $python;
}

test('a permanent python error fails the job immediately without retrying', function () {
    $run = Run::factory()->profiled()->create();
    $job = (new ProfileRunJob($run))->withFakeQueueInteractions();

    $job->handle(pythonClientThrowingOnProfile(new PythonServiceException('bad file', status: 400)));

    $job->assertFailed();

    $step = $run->steps()->sole();
    expect($step->status)->toBe('failed')
        ->and($step->result['error'])->toBe('bad file');
});

test('a transient python error is rethrown so the queue retries it, without failing the run yet', function () {
    $run = Run::factory()->profiled()->create();
    $job = (new ProfileRunJob($run))->withFakeQueueInteractions();

    expect(fn () => $job->handle(pythonClientThrowingOnProfile(new PythonServiceException('unavailable', status: 503, retryable: true))))
        ->toThrow(PythonServiceException::class);

    $job->assertNotFailed();
    expect($run->fresh()->status)->toBe(RunStatus::Profiling)
        ->and($run->steps()->sole()->status)->toBe('failed');
});

test('a missing cleaned file path is treated as a permanent failure', function () {
    $run = Run::factory()->profiled()->create();
    $job = (new CleanRunJob($run, [['action' => 'deduplicate']]))->withFakeQueueInteractions();

    $python = Mockery::mock(PythonServiceClient::class);
    $python->shouldReceive('clean')->andReturn(['status' => 'success', 'cleaned_file_path' => '']);

    $job->handle($python);

    $job->assertFailed();
    expect($run->fresh()->current_file_path)->toBeNull();
});

test('failed() marks the run failed and closes steps left running by a killed job', function () {
    $run = Run::factory()->withStatus(RunStatus::Profiling)->create();
    RunStep::create(['run_id' => $run->id, 'step_type' => StepType::Profile, 'status' => 'running']);

    (new ProfileRunJob($run))->failed(new RuntimeException('Job timed out.'));

    expect($run->fresh()->status)->toBe(RunStatus::Failed);

    $step = $run->steps()->sole();
    expect($step->status)->toBe('failed')
        ->and($step->result['error'])->toBe('Job timed out.');
});

test('on a real queue a permanent failure ends with the run marked failed', function () {
    $run = Run::factory()->profiled()->create();
    app()->instance(PythonServiceClient::class, pythonClientThrowingOnProfile(new PythonServiceException('bad file', status: 400)));

    ProfileRunJob::dispatchSync($run);

    expect($run->fresh()->status)->toBe(RunStatus::Failed);
});

test('job timeouts follow the python timeout and stay below the queue retry_after', function () {
    config(['services.python.timeout' => 100]);
    $run = Run::factory()->create();

    $jobs = [
        new ProfileRunJob($run),
        new CleanRunJob($run, []),
        new ValidateRunJob($run, SchemaContract::factory()->create()),
        new ExportRunJob($run, null, 'csv'),
    ];

    foreach ($jobs as $job) {
        expect($job->timeout)->toBe(130);
    }

    config(['services.python.timeout' => 240]);
    $longestTimeout = (new ProfileRunJob($run))->timeout;

    expect(config('queue.connections.database.retry_after'))->toBeGreaterThan($longestTimeout)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan($longestTimeout);
});
