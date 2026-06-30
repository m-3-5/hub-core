<?php

namespace M35\HubPayments\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
