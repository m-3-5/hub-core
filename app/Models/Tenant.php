<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    public function activePromo(): ?Promo
    {
        return $this->promos()
            ->where('status', 'published')
            ->where(function ($query) {
                $query->where('always_active', true)
                    ->orWhere(function ($q) {
                        $q->where(function ($inner) {
                            $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                        })->where(function ($inner) {
                            $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                        });
                    });
            })
            ->latest('published_at')
            ->first();
    }
}
