<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Promo extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'description',
        'offers',
        'cta_label',
        'cta_url',
        'image_path',
        'image_variants',
        'seo_title',
        'seo_description',
        'status',
        'always_active',
        'starts_at',
        'ends_at',
        'published_at',
        'ai_metadata',
    ];

    protected function casts(): array
    {
        return [
            'offers' => 'array',
            'always_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'ai_metadata' => 'array',
            'image_variants' => 'array',
        ];
    }

    public function variantUrl(?string $key = 'hero'): ?string
    {
        $variants = $this->image_variants ?? [];

        $path = match ($key) {
            'hero' => $variants['hero'] ?? $variants['hero_svg'] ?? $this->image_path,
            'og' => $variants['og'] ?? $variants['hero_svg'] ?? $this->image_path,
            'flyer' => $variants['flyer'] ?? $this->image_path,
            default => $variants[$key] ?? null,
        };

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query
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
            });
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    public function publicUrl(): string
    {
        return route('promo.show', [
            'tenant' => $this->tenant->slug,
            'promo' => $this->slug,
        ]);
    }
}
