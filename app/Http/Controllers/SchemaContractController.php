<?php

namespace App\Http\Controllers;

use App\Enums\ContractFormat;
use App\Models\SchemaContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchemaContractController extends Controller
{
    /**
     * Display a listing of schema contracts.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SchemaContract::class);

        $user = $request->user();

        $contracts = SchemaContract::query()
            ->when(! $user->hasRole('superadmin'), function ($query) use ($user) {
                $query->whereHas('users', fn ($q) => $q->where('user_id', $user->id));
            })
            ->with('creator:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('Contracts/Index', [
            'contracts' => $contracts,
        ]);
    }

    /**
     * Show the form for creating a new contract.
     */
    public function create(): Response
    {
        Gate::authorize('create', SchemaContract::class);

        return Inertia::render('Contracts/Create', [
            'formats' => collect(ContractFormat::cases())->map(fn ($f) => [
                'value' => $f->value,
                'label' => strtoupper($f->value),
            ]),
        ]);
    }

    /**
     * Store a newly created contract.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', SchemaContract::class);

        $validated = $request->validate([
            'dev_team_name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'string', 'in:csv,json,db'],
            'schema_definition' => ['required', 'json'],
        ]);

        SchemaContract::create([
            'dev_team_name' => $validated['dev_team_name'],
            'format' => $validated['format'],
            'schema_definition' => json_decode($validated['schema_definition'], true),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('contracts.index')
            ->with('success', 'Schema contract created.');
    }

    /**
     * Show the form for editing the contract.
     */
    public function edit(SchemaContract $contract): Response
    {
        Gate::authorize('update', $contract);

        return Inertia::render('Contracts/Edit', [
            'contract' => $contract,
            'formats' => collect(ContractFormat::cases())->map(fn ($f) => [
                'value' => $f->value,
                'label' => strtoupper($f->value),
            ]),
        ]);
    }

    /**
     * Update the specified contract.
     */
    public function update(Request $request, SchemaContract $contract): RedirectResponse
    {
        Gate::authorize('update', $contract);

        $validated = $request->validate([
            'dev_team_name' => ['required', 'string', 'max:255'],
            'format' => ['required', 'string', 'in:csv,json,db'],
            'schema_definition' => ['required', 'json'],
        ]);

        $contract->update([
            'dev_team_name' => $validated['dev_team_name'],
            'format' => $validated['format'],
            'schema_definition' => json_decode($validated['schema_definition'], true),
        ]);

        return redirect()->route('contracts.index')
            ->with('success', 'Schema contract updated.');
    }

    /**
     * Remove the specified contract.
     */
    public function destroy(SchemaContract $contract): RedirectResponse
    {
        Gate::authorize('delete', $contract);

        $contract->delete();

        return redirect()->route('contracts.index')
            ->with('success', 'Schema contract deleted.');
    }
}
