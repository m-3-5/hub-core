<?php

namespace App\Support;

use App\Models\Promo;
use App\Models\Tenant;

class PromoPublicPresenter
{
    /** @return array<string, mixed> */
    public static function tenant(Tenant $tenant): array
    {
        return [
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'website' => $tenant->website,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'primary_color' => $tenant->primary_color,
        ];
    }

    /** @return array<string, mixed> */
    public static function promo(Promo $promo): array
    {
        $promo->loadMissing('tenant');

        return [
            'id' => $promo->id,
            'title' => $promo->title,
            'slug' => $promo->slug,
            'description' => $promo->description,
            'offers' => $promo->offers ?? [],
            'cta_label' => $promo->cta_label,
            'cta_url' => $promo->cta_url,
            'image_url' => $promo->imageUrl(),
            'flyer_url' => $promo->variantUrl('flyer'),
            'hero_url' => $promo->variantUrl('hero_svg') ?? $promo->variantUrl('hero'),
            'public_url' => $promo->publicUrl(),
            'always_active' => $promo->always_active,
            'starts_at' => $promo->starts_at?->toIso8601String(),
            'ends_at' => $promo->ends_at?->toIso8601String(),
            'published_at' => $promo->published_at?->toIso8601String(),
        ];
    }
}
