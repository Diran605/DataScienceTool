<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Runs created by this user.
     */
    public function createdRuns(): HasMany
    {
        return $this->hasMany(Run::class, 'created_by');
    }

    /**
     * Schema contracts created by this user.
     */
    public function createdContracts(): HasMany
    {
        return $this->hasMany(SchemaContract::class, 'created_by');
    }

    /**
     * Schema contracts this user is scoped to via the contract_user pivot.
     */
    public function scopedContracts(): BelongsToMany
    {
        return $this->belongsToMany(SchemaContract::class, 'contract_user')
            ->withPivot(['can_run', 'can_view_deliveries'])
            ->withTimestamps();
    }
}
