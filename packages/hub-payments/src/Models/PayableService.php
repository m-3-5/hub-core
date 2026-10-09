<?php

namespace M35\HubPayments\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayableService extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by',
        'type',
        'title',
        'slug',
        'description',
        'cover_image_path',
        'amount_cents',
        'currency',
        'stripe_product_id',
        'stripe_price_id',
        'stripe_payment_link_id',
        'payment_url',
        'status',
        'published_to_site',
        'paid_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'published_to_site' => 'boolean',
            'paid_at' => 'datetime',
            'metadata' => 'array',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amountEuros(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', '.');
    }

    public function coverImageUrl(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_image_path);
    }

    /** Etichetta «In promo»: attiva finché la data di fine (metadata.promo_until) non è passata. */
    public function onPromo(): bool
    {
        $until = $this->promoUntil();

        return $until !== null && ! $until->isPast();
    }

    public function promoUntil(): ?\Illuminate\Support\Carbon
    {
        $value = $this->metadata['promo_until'] ?? null;

        return $value ? \Illuminate\Support\Carbon::parse($value)->endOfDay() : null;
    }

    public function durationMinutes(): ?int
    {
        $minutes = (int) ($this->metadata['duration_minutes'] ?? 0);

        return $minutes > 0 ? $minutes : null;
    }

    public function durationLabel(): ?string
    {
        $minutes = $this->durationMinutes();

        if (! $minutes) {
            return null;
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours.' h'.($rest ? ' '.$rest.' min' : '');
    }

    public function stripeImageUrl(): ?string
    {
        $url = $this->coverImageUrl();

        // Stripe accetta solo immagini raster: una grafica SVG resta sul sito ma non va a Stripe.
        if (! $url || str_ends_with(strtolower((string) $this->cover_image_path), '.svg')) {
            return null;
        }

        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return url($url);
    }

    /**
     * Indirizzo di pagamento per le pagine pubbliche di inm35.it: lo stesso Payment Link,
     * con il riferimento "hub" che Stripe ritorna nel pagamento (canale di vendita hub).
     */
    public function hubPaymentUrl(): ?string
    {
        if (! $this->payment_url) {
            return null;
        }

        return $this->payment_url.(str_contains($this->payment_url, '?') ? '&' : '?').'client_reference_id=hub';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->paid_at !== null;
    }

    public static function uniqueSlugForTenant(int $tenantId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'servizio';
        $candidate = $slug;
        $i = 2;

        while (static::query()
            ->where('tenant_id', $tenantId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }
}
