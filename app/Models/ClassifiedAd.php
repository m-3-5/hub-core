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
        // Oggetti e beni usati o nuovi: l'unica categoria acquistabile online con il pagamento protetto.
        'oggetti' => 'Oggetti in vendita',
        // Servizi offerti da persone e professionisti: traduzioni, lezioni, ripetizioni… (nessun pagamento protetto).
        'servizi' => 'Servizi e lezioni',
    ];

    public const PRICE_UNITS = [
        '' => 'Prezzo fisso',
        'mese' => 'al mese',
        'settimana' => 'a settimana',
        'notte' => 'a notte',
        'ora' => 'all\'ora',
        'pagina' => 'a pagina',
    ];

    public const FEATURE_LABELS = [
        'rooms' => 'Locali',
        'beds' => 'Posti letto',
        'bathrooms' => 'Bagni',
        'sqm' => 'm²',
    ];

    protected $fillable = [
        'tenant_id', 'slug', 'category', 'title', 'zone', 'price', 'price_unit', 'protected_sale', 'description',
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
            'protected_sale' => 'boolean',
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

    /** L'annuncio può essere comprato online con il pagamento protetto: oggetto a prezzo fisso, pubblicato, con l'opzione attiva. */
    public function offersProtectedSale(): bool
    {
        return $this->protected_sale
            && $this->category === 'oggetti'
            && $this->isPublished()
            && $this->price !== null && (float) $this->price > 0
            && empty($this->price_unit);
    }

    public function priceCents(): int
    {
        return (int) round(((float) $this->price) * 100);
    }

    public function isSold(): bool
    {
        return $this->status === 'sold';
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
