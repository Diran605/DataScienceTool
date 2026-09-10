<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users with their roles.
     */
    public function index(): Response
    {
        $users = User::with('roles')
            ->latest()
            ->paginate(20);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for editing a user's roles and contract access.
     */
    public function edit(User $user): Response
    {
        $user->load(['roles', 'scopedContracts']);

        $roles = Role::all(['id', 'name']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
            'userRoles' => $user->roles->pluck('name'),
            'userContracts' => $user->scopedContracts->map(fn ($c) => [
                'id' => $c->id,
                'dev_team_name' => $c->dev_team_name,
                'can_run' => (bool) $c->pivot->can_run,
                'can_view_deliveries' => (bool) $c->pivot->can_view_deliveries,
            ]),
        ]);
    }

    /**
     * Update the user's roles.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($validated['roles']);

        return redirect()->route('admin.users.index')
            ->with('success', "Roles updated for {$user->name}.");
    }
}
