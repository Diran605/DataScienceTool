<?php

namespace App\Policies;

use App\Models\SchemaContract;
use App\Models\User;

class SchemaContractPolicy
{
    /**
     * Superadmin bypasses all contract scoping.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any contracts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('contracts.manage')
            || $user->scopedContracts()->exists();
    }

    /**
     * Determine whether the user can view the contract.
     */
    public function view(User $user, SchemaContract $schemaContract): bool
    {
        return $user->scopedContracts()
            ->where('schema_contract_id', $schemaContract->id)
            ->exists();
    }

    /**
     * Determine whether the user can create contracts.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('contracts.manage');
    }

    /**
     * Determine whether the user can update the contract.
     */
    public function update(User $user, SchemaContract $schemaContract): bool
    {
        return $user->hasPermissionTo('contracts.manage');
    }

    /**
     * Determine whether the user can delete the contract.
     */
    public function delete(User $user, SchemaContract $schemaContract): bool
    {
        return $user->hasPermissionTo('contracts.manage');
    }
}
