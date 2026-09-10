<?php

use App\Models\SchemaContract;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->superadmin = User::factory()->create();
    $this->superadmin->assignRole('superadmin');

    $this->user = User::factory()->create();
});

test('only superadmins can access admin index', function () {
    $this->actingAs($this->user)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($this->superadmin)
        ->get(route('admin.users.index'))
        ->assertOk();
});

test('superadmins can update user roles', function () {
    $role = Role::firstOrCreate(['name' => 'operator']);

    $this->actingAs($this->superadmin)
        ->put(route('admin.users.update', $this->user), [
            'roles' => ['operator'],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($this->user->fresh()->hasRole('operator'))->toBeTrue();
});

test('superadmins can assign contract access to users', function () {
    $contract = SchemaContract::factory()->create();

    $this->actingAs($this->superadmin)
        ->post(route('admin.users.contracts.store', $this->user), [
            'schema_contract_id' => $contract->id,
            'can_run' => true,
            'can_view_deliveries' => false,
        ])
        ->assertRedirect();

    $this->user->refresh();

    $userContract = $this->user->scopedContracts->first();
    expect($userContract)->not->toBeNull()
        ->and($userContract->id)->toBe($contract->id)
        ->and($userContract->pivot->can_run)->toBe(1)
        ->and($userContract->pivot->can_view_deliveries)->toBe(0);
});

test('superadmins can revoke contract access', function () {
    $contract = SchemaContract::factory()->create();
    $this->user->scopedContracts()->attach($contract->id, [
        'can_run' => true,
        'can_view_deliveries' => true,
    ]);

    $this->actingAs($this->superadmin)
        ->delete(route('admin.users.contracts.destroy', [$this->user, $contract]))
        ->assertRedirect();

    expect($this->user->fresh()->scopedContracts)->toBeEmpty();
});
