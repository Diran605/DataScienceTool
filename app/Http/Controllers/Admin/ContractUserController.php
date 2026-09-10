<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchemaContract;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractUserController extends Controller
{
    /**
     * Grant a user access to a contract.
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'schema_contract_id' => ['required', 'exists:schema_contracts,id'],
            'can_run' => ['boolean'],
            'can_view_deliveries' => ['boolean'],
        ]);

        $user->scopedContracts()->syncWithoutDetaching([
            $validated['schema_contract_id'] => [
                'can_run' => $validated['can_run'] ?? false,
                'can_view_deliveries' => $validated['can_view_deliveries'] ?? false,
            ],
        ]);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Contract access granted.');
    }

    /**
     * Revoke a user's access to a contract.
     */
    public function destroy(User $user, SchemaContract $contract): RedirectResponse
    {
        $user->scopedContracts()->detach($contract->id);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', 'Contract access revoked.');
    }
}
