<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\User;

class DeliveryPolicy
{
    /**
     * Superadmin bypasses all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any deliveries.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('deliveries.view');
    }

    /**
     * Determine whether the user can view the delivery.
     */
    public function view(User $user, Delivery $delivery): bool
    {
        if (! $user->hasPermissionTo('deliveries.view')) {
            return false;
        }

        // Check contract scoping — user must have can_view_deliveries on the contract
        return $user->scopedContracts()
            ->where('schema_contract_id', $delivery->schema_contract_id)
            ->wherePivot('can_view_deliveries', true)
            ->exists();
    }
}
