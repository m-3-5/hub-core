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
            'hero' => $variants['hero'] ?? $this->image_path,
            'og' => $variants['og'] ?? $variants['hero'] ?? $this->image_path,
            'flyer' => $variants['flyer'] ?? $this->image_path,
            default => $variants[$key] ?? null,
        };

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** @return array<int, array{key: string, label: string, url: string}> */
    public function decorImages(): array
    {
        $items = [];

        foreach ($this->image_variants['decor'] ?? [] as $slot => $meta) {
            $path = is_array($meta) ? ($meta['path'] ?? null) : $meta;

            if (! $path) {
                continue;
            }

            $items[] = [
                'key' => is_array($meta) ? ($meta['key'] ?? $slot) : $slot,
                'label' => is_array($meta) ? ($meta['label'] ?? '') : '',
                'url' => Storage::disk('public')->url($path),
                'slot' => $slot,
            ];
        }

        return $items;
    }

    public function decorUrlForOffer(int $index): ?string
    {
        $slot = "offer-{$index}";
        $meta = $this->image_variants['decor'][$slot] ?? null;
        $path = is_array($meta) ? ($meta['path'] ?? null) : $meta;

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

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeExpired($query)
    {
        return $query
            ->published()
            ->where('always_active', false)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now());
    }

    public function isActive(): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        if ($this->always_active) {
            return true;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->isPublished()
            && ! $this->always_active
            && $this->ends_at
            && $this->ends_at->isPast();
    }

    public function expiryLabel(): ?string
    {
        if ($this->always_active) {
            return 'Sempre valida';
        }

        if ($this->ends_at) {
            return $this->isExpired()
                ? 'Scaduta il '.$this->ends_at->timezone(config('app.timezone'))->format('d/m/Y')
                : 'Valida fino al '.$this->ends_at->timezone(config('app.timezone'))->format('d/m/Y');
        }

        return null;
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

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function flyerSvgPath(): ?string
    {
        $metaPath = $this->ai_metadata['flyer_svg_path'] ?? null;

        if (is_string($metaPath) && Storage::disk('public')->exists($metaPath)) {
            return $metaPath;
        }

        if (is_string($this->image_path) && str_ends_with($this->image_path, '.svg') && Storage::disk('public')->exists($this->image_path)) {
            return $this->image_path;
        }

        return null;
    }

    public function templateView(): string
    {
        return ($this->ai_metadata['landing_style'] ?? null) === 'agency'
            ? 'promo.show-agency'
            : 'promo.show';
    }
}
