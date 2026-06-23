<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use App\Notifications\ResetPasswordNotification;

#[Fillable(['name', 'email', 'password', 'is_super_admin', 'wp_username'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenants()->where('tenants.id', $tenant->id)->exists();
    }

    public function tenantRole(Tenant $tenant): ?string
    {
        $pivot = $this->tenants()->where('tenants.id', $tenant->id)->first();

        return $pivot?->pivot?->role;
    }

    /** @return Collection<int, Tenant> */
    public function accessibleTenants(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Tenant::orderBy('name')->get();
        }

        return $this->tenants()->orderBy('name')->get();
    }

    public static function findByWpUsername(string $username): ?self
    {
        return static::query()
            ->where('wp_username', strtolower($username))
            ->first();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
