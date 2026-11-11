<?php

use App\Enums\RunStatus;
use App\Jobs\ProfileRunJob;
use App\Models\Run;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
    Storage::fake('data-processing');
    Queue::fake();
});

function uploader(): User
{
    $user = User::factory()->create();
    $user->assignRole('operator');

    return $user;
}

test('tabular files are uploaded, stored and queued for profiling', function (string $filename) {
    $user = uploader();

    actingAs($user)
        ->post(route('runs.store'), ['file' => UploadedFile::fake()->create($filename, 10)])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $run = Run::sole();

    expect($run->status)->toBe(RunStatus::Pending)
        ->and($run->created_by)->toBe($user->id)
        ->and($run->source_file_type)->toBe(pathinfo($filename, PATHINFO_EXTENSION));

    Storage::disk('data-processing')->assertExists($run->source_file_path);
    Queue::assertPushed(ProfileRunJob::class, fn ($job) => $job->run->is($run));
})->with(['data.csv', 'data.xlsx', 'data.xls', 'data.parquet']);

test('files with unsupported extensions are rejected', function () {
    actingAs(uploader())
        ->post(route('runs.store'), ['file' => UploadedFile::fake()->create('script.exe', 10)])
        ->assertSessionHasErrors('file');

    expect(Run::count())->toBe(0);
    Queue::assertNothingPushed();
});
