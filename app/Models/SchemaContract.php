<?php

namespace App\Models;

use App\Enums\ContractFormat;
use Database\Factories\SchemaContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['dev_team_name', 'format', 'schema_definition', 'created_by'])]
class SchemaContract extends Model
{
    /** @use HasFactory<SchemaContractFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ContractFormat::class,
            'schema_definition' => 'array',
        ];
    }

    /**
     * The user who created this contract.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Users scoped to this contract via the pivot table.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contract_user')
            ->withPivot(['can_run', 'can_view_deliveries'])
            ->withTimestamps();
    }

    /**
     * Limit to contracts the user may validate/export against. Superadmins see all.
     */
    #[Scope]
    protected function runnableBy(Builder $query, User $user): void
    {
        if ($user->hasRole('superadmin')) {
            return;
        }

        $query->whereHas('users', fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->where('contract_user.can_run', true));
    }

    /**
     * Deliveries associated with this contract.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
