<?php

use App\Enums\RunStatus;
use App\Jobs\CleanRunJob;
use App\Jobs\ExportRunJob;
use App\Jobs\ValidateRunJob;
use App\Models\Run;
use App\Models\SchemaContract;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('operator');
});

test('users can dispatch clean job if run is profiled', function () {
    Queue::fake();

    $run = Run::factory()->profiled()->create([
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
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
    $run = Run::factory()->withStatus(RunStatus::Pending)->create([
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->put(route('runs.clean.update', $run), [
            'steps' => [['action' => 'drop_nulls']],
        ])
        ->assertStatus(422);
});

test('users can dispatch validate job', function () {
    Queue::fake();

    $run = Run::factory()->profiled()->create([
        'created_by' => $this->user->id,
    ]);

    $contract = SchemaContract::factory()->create();
    $this->user->scopedContracts()->attach($contract->id, ['can_run' => true]);

    $this->actingAs($this->user)
        ->post(route('runs.validate.store', $run), [
            'schema_contract_id' => $contract->id,
        ])
        ->assertRedirect(route('runs.show', $run));

    Queue::assertPushed(ValidateRunJob::class, function ($job) use ($run, $contract) {
        return $job->run->id === $run->id && $job->contract->id === $contract->id;
    });
});

test('users can dispatch export job', function () {
    Queue::fake();

    $run = Run::factory()->withStatus(RunStatus::Profiled)->create([
        'created_by' => $this->user->id,
    ]);

    $contract = SchemaContract::factory()->create();

    $this->actingAs($this->user)
        ->post(route('runs.export.store', $run), [
            'schema_contract_id' => $contract->id,
            'format' => 'csv',
        ])
        ->assertRedirect(route('runs.show', $run));

    Queue::assertPushed(ExportRunJob::class, function ($job) use ($run, $contract) {
        return $job->run->id === $run->id
            && $job->contract->id === $contract->id
            && $job->format === 'csv';
    });
});
