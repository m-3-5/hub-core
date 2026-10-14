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
        // Libri e altri prodotti che si comprano altrove (es. Amazon): l'annuncio porta al link, non c'è pagamento da noi.
        'libri' => 'Libri',
    ];

    /**
     * Cosa serve a ciascuna categoria: icona, campi specifici (chiavi di FEATURE_LABELS / TEXT_FEATURES),
     * unità di prezzo che hanno senso e il suggerimento per la descrizione.
     */
    public const CATEGORY_META = [
        'affitto' => ['icon' => '🏠', 'fields' => ['rooms', 'beds', 'bathrooms', 'sqm'], 'units' => ['', 'mese', 'settimana'],
            'hint' => "Descrivi l'immobile: servizi, distanza dal centro, periodo disponibile, regole..."],
        'vacanze' => ['icon' => '🏖️', 'fields' => ['rooms', 'beds', 'bathrooms', 'sqm'], 'units' => ['', 'notte', 'settimana', 'mese'],
            'hint' => 'Descrivi la casa vacanza: posizione, servizi, periodi disponibili, regole della casa...'],
        'vendita' => ['icon' => '🏡', 'fields' => ['rooms', 'bathrooms', 'sqm'], 'units' => [''],
            'hint' => "Descrivi l'immobile: zona, piano, stato, spese, dotazioni..."],
        'oggetti' => ['icon' => '🛍️', 'fields' => ['condition'], 'units' => [''],
            'hint' => "Descrivi l'oggetto: marca, stato, misure, motivo della vendita, ritiro o spedizione..."],
        'servizi' => ['icon' => '🎓', 'fields' => ['mode'], 'units' => ['', 'ora', 'pagina'],
            'hint' => 'Descrivi il servizio: cosa offri, per chi, come lavori, come prenotare...'],
        'libri' => ['icon' => '📚', 'fields' => ['author', 'buy_url'], 'units' => [''],
            'hint' => 'Di cosa parla il libro, a chi è rivolto, lingua, numero di pagine, formato (eBook o cartaceo)...'],
    ];

    /** Campi di testo libero / link specifici di alcune categorie. */
    public const INPUT_FEATURES = [
        'author' => ['label' => 'Autore', 'type' => 'text', 'max' => 120, 'placeholder' => 'Nome e cognome'],
        'buy_url' => ['label' => "Link per l'acquisto (Amazon o altro sito)", 'type' => 'url', 'max' => 500, 'placeholder' => 'https://www.amazon.it/...'],
    ];

    /** Campi a scelta (non numerici) specifici di alcune categorie. */
    public const TEXT_FEATURES = [
        'condition' => ['label' => 'Condizione', 'options' => ['nuovo' => 'Nuovo', 'come_nuovo' => 'Come nuovo', 'usato' => 'Usato']],
        'mode' => ['label' => 'Modalità', 'options' => ['online' => 'Online', 'presenza' => 'In presenza', 'entrambe' => 'Online e in presenza']],
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

    public static function iconFor(?string $category): string
    {
        return self::CATEGORY_META[$category]['icon'] ?? '📌';
    }

    public function categoryIcon(): string
    {
        return self::iconFor($this->category);
    }

    /** @return array<int, string> voci da mostrare (es. «3 Locali», «Condizione: Usato»), solo quelle compilate */
    public function featureChips(): array
    {
        $chips = [];

        foreach (self::FEATURE_LABELS as $key => $label) {
            if (! empty($this->features[$key])) {
                $chips[] = $this->features[$key].' '.$label;
            }
        }

        foreach (self::TEXT_FEATURES as $key => $meta) {
            $value = $this->features[$key] ?? null;

            if ($value && isset($meta['options'][$value])) {
                $chips[] = $meta['label'].': '.$meta['options'][$value];
            }
        }

        if (! empty($this->features['author'])) {
            $chips[] = 'Autore: '.$this->features['author'];
        }

        return $chips;
    }

    /** Link esterno dove si compra (solo http/https), oppure null. */
    public function buyUrl(): ?string
    {
        $url = $this->features['buy_url'] ?? null;

        return is_string($url) && preg_match('~^https?://~i', $url) ? $url : null;
    }

    public function buyLabel(): string
    {
        $host = strtolower((string) parse_url((string) $this->buyUrl(), PHP_URL_HOST));

        return str_contains($host, 'amazon.') || str_contains($host, 'amzn.') ? 'Acquista su Amazon' : 'Vai al sito per acquistare';
    }

    public function publicUrl(): string
    {
        return route('classifieds.show', [$this->tenant, $this]);
    }
}
