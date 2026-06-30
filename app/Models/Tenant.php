<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'domain',
        'website',
        'phone',
        'address',
        'primary_color',
        'plan',
        'workspace_database',
        'workspace_url',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function promos(): HasMany
    {
        return $this->hasMany(Promo::class);
    }

    public function payableServices(): HasMany
    {
        return $this->hasMany(\M35\HubPayments\Models\PayableService::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function activePromo(): ?Promo
    {
        return $this->promos()->active()->latest('published_at')->first();
    }

    public function isDedicated(): bool
    {
        return $this->plan === 'dedicated';
    }

    public function workspaceConnectionName(): ?string
    {
        if (! $this->isDedicated() || ! $this->workspace_database) {
            return null;
        }

        return 'tenant_workspace_'.$this->slug;
    }
}
