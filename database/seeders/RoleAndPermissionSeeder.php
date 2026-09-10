<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Seed the roles and permissions defined in the project spec.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'runs.create',
            'runs.view',
            'runs.delete',
            'contracts.manage',
            'users.manage',
            'deliveries.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Flush the cache again after creating permissions so Role::syncPermissions can find them
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles and assign permissions
        $superadmin = Role::findOrCreate('superadmin', 'web');
        $superadmin->syncPermissions($permissions);

        $operator = Role::findOrCreate('operator', 'web');
        $operator->syncPermissions([
            'runs.create',
            'runs.view',
            'deliveries.view',
        ]);

        $viewer = Role::findOrCreate('viewer', 'web');
        $viewer->syncPermissions([
            'runs.view',
            'deliveries.view',
        ]);
    }
}
