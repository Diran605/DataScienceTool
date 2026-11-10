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
use App\Models\User;
use App\Services\PythonServiceClient;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

/**
 * Create a user with the given role.
 */
function userWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Record a completed validation step on the run.
 */
function recordValidation(Run $run, SchemaContract $contract, bool $isValid): RunStep
{
    return RunStep::create([
        'run_id' => $run->id,
        'step_type' => StepType::Validate,
        'input_file_path' => $run->workingFilePath(),
        'config' => ['schema_contract_id' => $contract->id],
        'result' => ['is_valid' => $isValid, 'errors' => []],
        'status' => 'completed',
    ]);
}

/**
 * Record a completed (non-preview) clean step on the run.
 */
function recordClean(Run $run, string $outputPath): RunStep
{
    return RunStep::create([
        'run_id' => $run->id,
        'step_type' => StepType::Clean,
        'input_file_path' => $run->source_file_path,
        'output_file_path' => $outputPath,
        'config' => ['steps' => []],
        'result' => ['cleaned_file_path' => $outputPath],
        'status' => 'completed',
    ]);
}

// --- Dispatching -----------------------------------------------------------

test('users can dispatch clean job if run is profiled', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->profiled()->create([
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->put(route('runs.clean.update', $run), [
            'steps' => [
                ['action' => 'drop_nulls', 'columns' => ['age']],
            ],
        ])
        ->assertRedirect(route('runs.show', $run));

    Queue::assertPushed(CleanRunJob::class, function ($job) use ($run) {
        return $job->run->id === $run->id && count($job->steps) === 1;
    });
});

test('cannot dispatch clean if run is not profiled', function () {
    $user = userWithRole('operator');

    $run = Run::factory()->withStatus(RunStatus::Pending)->create([
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->put(route('runs.clean.update', $run), [
            'steps' => [['action' => 'drop_nulls']],
        ])
        ->assertStatus(422);
});

test('cannot dispatch clean while another job is in flight', function () {
    $user = userWithRole('operator');

    $run = Run::factory()->withStatus(RunStatus::Exporting)->create([
        'created_by' => $user->id,
    ]);

    actingAs($user)
        ->put(route('runs.clean.update', $run), [
            'steps' => [['action' => 'drop_nulls']],
        ])
        ->assertStatus(422);
});

test('users can dispatch validate job', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->profiled()->create([
        'created_by' => $user->id,
    ]);

    $contract = SchemaContract::factory()->create();
    $user->scopedContracts()->attach($contract->id, ['can_run' => true]);

    actingAs($user)
        ->post(route('runs.validate.store', $run), [
            'schema_contract_id' => $contract->id,
        ])
        ->assertRedirect(route('runs.show', $run));

    Queue::assertPushed(ValidateRunJob::class, function ($job) use ($run, $contract) {
        return $job->run->id === $run->id && $job->contract->id === $contract->id;
    });
});

test('cannot validate against a contract the user is not allowed to run', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->profiled()->create(['created_by' => $user->id]);
    $contract = SchemaContract::factory()->create();
    $user->scopedContracts()->attach($contract->id, ['can_run' => false]);

    actingAs($user)
        ->post(route('runs.validate.store', $run), ['schema_contract_id' => $contract->id])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

// --- Export gating ---------------------------------------------------------

test('users can export data that passed validation against the chosen contract', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->withStatus(RunStatus::Validated)->create([
        'created_by' => $user->id,
    ]);

    $contract = SchemaContract::factory()->create();
    $user->scopedContracts()->attach($contract->id, ['can_run' => true]);
    recordValidation($run, $contract, true);

    actingAs($user)
        ->post(route('runs.export.store', $run), [
            'schema_contract_id' => $contract->id,
            'format' => 'csv',
            'override_reason' => 'not needed',
        ])
        ->assertRedirect(route('runs.show', $run));

    Queue::assertPushed(ExportRunJob::class, function ($job) use ($run, $contract) {
        return $job->run->id === $run->id
            && $job->contract->id === $contract->id
            && $job->format === 'csv'
            && $job->overrideReason === null;
    });
});

test('export is rejected after a failed validation without an override reason', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->withStatus(RunStatus::ValidationFailed)->create([
        'created_by' => $user->id,
    ]);

    $contract = SchemaContract::factory()->create();
    $user->scopedContracts()->attach($contract->id, ['can_run' => true]);
    recordValidation($run, $contract, false);

    actingAs($user)
        ->post(route('runs.export.store', $run), [
            'schema_contract_id' => $contract->id,
            'format' => 'csv',
        ])
        ->assertSessionHasErrors('override_reason');

    Queue::assertNothingPushed();
});

test('export after a failed validation is allowed with an override reason, which is passed to the job', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->withStatus(RunStatus::ValidationFailed)->create([
        'created_by' => $user->id,
    ]);

    $contract = SchemaContract::factory()->create();
    $user->scopedContracts()->attach($contract->id, ['can_run' => true]);
    recordValidation($run, $contract, false);

    actingAs($user)
        ->post(route('runs.export.store', $run), [
            'schema_contract_id' => $contract->id,
            'format' => 'json',
            'override_reason' => 'Team accepted known null emails.',
        ])
        ->assertRedirect(route('runs.show', $run));

    Queue::assertPushed(ExportRunJob::class, fn ($job) => $job->overrideReason === 'Team accepted known null emails.');
});

test('a validation against a different contract does not unlock export', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->withStatus(RunStatus::Validated)->create(['created_by' => $user->id]);

    $validatedContract = SchemaContract::factory()->create();
    $otherContract = SchemaContract::factory()->create();
    $user->scopedContracts()->attach([
        $validatedContract->id => ['can_run' => true],
        $otherContract->id => ['can_run' => true],
    ]);
    recordValidation($run, $validatedContract, true);

    actingAs($user)
        ->post(route('runs.export.store', $run), [
            'schema_contract_id' => $otherContract->id,
            'format' => 'csv',
        ])
        ->assertSessionHasErrors('override_reason');

    Queue::assertNothingPushed();
});

test('cleaning after a passing validation invalidates it', function () {
    $user = userWithRole('operator');

    $run = Run::factory()->withStatus(RunStatus::Cleaned)->create(['created_by' => $user->id]);
    $contract = SchemaContract::factory()->create();

    recordValidation($run, $contract, true);
    expect($run->hasPassedValidation($contract))->toBeTrue();

    recordClean($run, 'uploads/cleaned_after.csv');
    expect($run->hasPassedValidation($contract))->toBeFalse();
});

test('a clean preview does not invalidate a passing validation', function () {
    $user = userWithRole('operator');

    $run = Run::factory()->withStatus(RunStatus::Validated)->create(['created_by' => $user->id]);
    $contract = SchemaContract::factory()->create();

    recordValidation($run, $contract, true);
    RunStep::create([
        'run_id' => $run->id,
        'step_type' => StepType::Clean,
        'input_file_path' => $run->source_file_path,
        'config' => ['steps' => []],
        'result' => ['cleaned_file_path' => ''],
        'status' => 'completed',
    ]);

    expect($run->hasPassedValidation($contract))->toBeTrue();
});

test('export page only lists contracts the user can run', function () {
    $user = userWithRole('operator');

    $run = Run::factory()->profiled()->create(['created_by' => $user->id]);

    $runnable = SchemaContract::factory()->create();
    $viewOnly = SchemaContract::factory()->create();
    SchemaContract::factory()->create(); // unrelated contract

    $user->scopedContracts()->attach([
        $runnable->id => ['can_run' => true],
        $viewOnly->id => ['can_run' => false],
    ]);

    actingAs($user)
        ->get(route('runs.export', $run))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Runs/Export')
            ->has('contracts', 1)
            ->where('contracts.0.id', $runnable->id)
            ->where('hasPassedValidation', false));
});

// --- Authorization ---------------------------------------------------------

test('viewers cannot clean, validate, export or reprofile runs', function () {
    Queue::fake();

    $viewer = userWithRole('viewer');

    $run = Run::factory()->profiled()->create(['created_by' => $viewer->id]);
    $contract = SchemaContract::factory()->create();
    $viewer->scopedContracts()->attach($contract->id, ['can_run' => true]);

    actingAs($viewer);

    get(route('runs.clean', $run))->assertForbidden();
    put(route('runs.clean.update', $run), ['steps' => [['action' => 'drop_nulls']]])->assertForbidden();
    post(route('runs.validate.store', $run), ['schema_contract_id' => $contract->id])->assertForbidden();
    post(route('runs.export.store', $run), ['format' => 'csv', 'override_reason' => 'x'])->assertForbidden();
    post(route('runs.reprofile', $run))->assertForbidden();

    Queue::assertNothingPushed();
});

test('run page tells the frontend whether the user can process the run', function () {
    $user = userWithRole('operator');

    $viewer = userWithRole('viewer');
    $run = Run::factory()->profiled()->create(['created_by' => $user->id]);

    actingAs($user)
        ->get(route('runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page->where('canProcess', true));

    actingAs($viewer)
        ->get(route('runs.show', $run))
        ->assertInertia(fn (Assert $page) => $page->where('canProcess', false));
});

test('dashboard in-progress count includes cleaned and validated runs', function () {
    $user = userWithRole('operator');

    foreach ([RunStatus::Profiled, RunStatus::Cleaned, RunStatus::Validated, RunStatus::ValidationFailed, RunStatus::Delivered, RunStatus::Failed] as $status) {
        Run::factory()->withStatus($status)->create(['created_by' => $user->id]);
    }

    actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.profiled', 4)
            ->where('stats.failed', 1));
});

test('operators cannot process runs created by someone else', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $otherOperator = userWithRole('operator');
    $run = Run::factory()->profiled()->create(['created_by' => $otherOperator->id]);

    actingAs($user)
        ->put(route('runs.clean.update', $run), ['steps' => [['action' => 'drop_nulls']]])
        ->assertForbidden();

    Queue::assertNothingPushed();
});

// --- Job behaviour / lineage -----------------------------------------------

test('cleaning twice always starts from the original upload and never overwrites it', function () {
    $user = userWithRole('operator');

    Queue::fake();

    $run = Run::factory()->profiled()->create([
        'created_by' => $user->id,
        'source_file_path' => 'uploads/original.csv',
    ]);

    $python = Mockery::mock(PythonServiceClient::class);
    $python->shouldReceive('clean')
        ->twice()
        ->with('uploads/original.csv', Mockery::any(), Mockery::any())
        ->andReturn(
            ['cleaned_file_path' => 'uploads/cleaned_1.csv'],
            ['cleaned_file_path' => 'uploads/cleaned_2.csv'],
        );

    (new CleanRunJob($run, [['action' => 'drop_nulls']]))->handle($python);
    (new CleanRunJob($run->fresh(), [['action' => 'drop_nulls'], ['action' => 'deduplicate']]))->handle($python);

    $run->refresh();

    expect($run->source_file_path)->toBe('uploads/original.csv')
        ->and($run->current_file_path)->toBe('uploads/cleaned_2.csv')
        ->and($run->workingFilePath())->toBe('uploads/cleaned_2.csv');

    $cleanSteps = $run->steps()->where('step_type', StepType::Clean)->orderBy('id')->get();
    expect($cleanSteps->pluck('input_file_path')->all())->toBe(['uploads/original.csv', 'uploads/original.csv'])
        ->and($cleanSteps->pluck('output_file_path')->all())->toBe(['uploads/cleaned_1.csv', 'uploads/cleaned_2.csv']);

    Queue::assertPushed(ProfileRunJob::class, 2);
});

test('profiling a cleaned run profiles the cleaned file and marks it cleaned', function () {
    $user = userWithRole('operator');

    $run = Run::factory()->withStatus(RunStatus::Cleaning)->create([
        'created_by' => $user->id,
        'source_file_path' => 'uploads/original.csv',
        'current_file_path' => 'uploads/cleaned_1.csv',
    ]);

    $python = Mockery::mock(PythonServiceClient::class);
    $python->shouldReceive('profile')->once()->with('uploads/cleaned_1.csv')->andReturn(['columns' => []]);

    (new ProfileRunJob($run))->handle($python);

    expect($run->fresh()->status)->toBe(RunStatus::Cleaned);
});

test('validation job sets validated or validation_failed from the python result', function (bool $isValid, RunStatus $expected) {
    $user = userWithRole('operator');

    $run = Run::factory()->withStatus(RunStatus::Cleaned)->create([
        'created_by' => $user->id,
        'current_file_path' => 'uploads/cleaned_1.csv',
    ]);
    $contract = SchemaContract::factory()->create();

    $python = Mockery::mock(PythonServiceClient::class);
    $python->shouldReceive('validate')
        ->once()
        ->with('uploads/cleaned_1.csv', Mockery::any())
        ->andReturn(['is_valid' => $isValid, 'errors' => []]);

    (new ValidateRunJob($run, $contract))->handle($python);

    expect($run->fresh()->status)->toBe($expected);
})->with([
    'passing' => [true, RunStatus::Validated],
    'failing' => [false, RunStatus::ValidationFailed],
]);

test('deleting a run removes the original upload and all cleaned files', function () {
    $user = userWithRole('operator');

    $disk = Storage::fake('data-processing');
    $disk->put('uploads/original.csv', 'a,b');
    $disk->put('uploads/cleaned_1.csv', 'a,b');
    $disk->put('uploads/cleaned_2.csv', 'a,b');

    $run = Run::factory()->withStatus(RunStatus::Cleaned)->create([
        'created_by' => $user->id,
        'source_file_path' => 'uploads/original.csv',
        'current_file_path' => 'uploads/cleaned_2.csv',
    ]);
    recordClean($run, 'uploads/cleaned_1.csv');
    recordClean($run, 'uploads/cleaned_2.csv');

    actingAs($user)
        ->delete(route('runs.destroy', $run))
        ->assertRedirect(route('runs.index'));

    $disk->assertMissing(['uploads/original.csv', 'uploads/cleaned_1.csv', 'uploads/cleaned_2.csv']);
});
