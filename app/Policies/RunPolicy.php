<?php

namespace App\Policies;

use App\Models\Run;
use App\Models\User;

class RunPolicy
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
     * Determine whether the user can view any runs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('runs.view');
    }

    /**
     * Determine whether the user can view the run.
     */
    public function view(User $user, Run $run): bool
    {
        return $user->hasPermissionTo('runs.view');
    }

    /**
     * Determine whether the user can create runs.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('runs.create');
    }

    /**
     * Determine whether the user can process the run (reprofile, clean, validate, export).
     * Viewers can look at runs but never change their data.
     */
    public function process(User $user, Run $run): bool
    {
        return $user->hasPermissionTo('runs.create')
            && $run->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the run.
     */
    public function delete(User $user, Run $run): bool
    {
        if ($user->hasPermissionTo('runs.delete')) {
            return true;
        }

        // Owners can delete their own runs
        return $run->created_by === $user->id;
    }
}
