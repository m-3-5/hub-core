<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClassifiedAd extends Model
{
    // Fase 1: solo immobili. Per aggiungere altri tipi di annuncio basta una riga qui
    // (eventuali campi specifici vanno in FEATURE_LABELS / colonna "features").
    public const CATEGORIES = [
        'affitto' => 'Affitto',
        'vacanze' => 'Case vacanza',
        'vendita' => 'Vendita immobili',
    ];

    public const PRICE_UNITS = [
        '' => 'Prezzo fisso',
        'mese' => 'al mese',
        'settimana' => 'a settimana',
        'notte' => 'a notte',
    ];

    public const FEATURE_LABELS = [
        'rooms' => 'Locali',
        'beds' => 'Posti letto',
        'bathrooms' => 'Bagni',
        'sqm' => 'm²',
    ];

    protected $fillable = [
        'tenant_id', 'slug', 'category', 'title', 'zone', 'price', 'price_unit', 'description',
        'features', 'images', 'videos', 'contact_name', 'contact_phone', 'contact_email', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'images' => 'array',
            'videos' => 'array',
            'published_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    /** @return array<int, string> */
    public function imageUrls(): array
    {
        return collect($this->images ?? [])
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->all();
    }

    /** @return array<int, string> */
    public function videoUrls(): array
    {
        return collect($this->videos ?? [])
            ->map(fn (string $path) => Storage::disk('public')->url($path))
            ->all();
    }

    public function coverUrl(): ?string
    {
        return $this->imageUrls()[0] ?? null;
    }

    public function priceLabel(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        $amount = rtrim(rtrim(number_format((float) $this->price, 2, ',', '.'), '0'), ',');
        $unit = $this->price_unit ? ' '.(self::PRICE_UNITS[$this->price_unit] ?? '') : '';

        return '€ '.$amount.$unit;
    }

    public function publicUrl(): string
    {
        return route('classifieds.show', [$this->tenant, $this]);
    }
}
