<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteOrder extends Model
{
    protected $fillable = [
        'site_lead_id', 'name', 'phone', 'email', 'plan', 'start_cents', 'trial_days', 'installments', 'installment_cents',
        'package_cents', 'status', 'stripe_session_id', 'stripe_customer_id', 'stripe_subscription_id',
        'paid_at', 'trial_ends_at', 'completes_at',
    ];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'trial_ends_at' => 'datetime', 'completes_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(SiteLead::class, 'site_lead_id');
    }

    public function planLabel(): string
    {
        return collect(config('landing.plans'))->pluck('name', 'key')->all()[$this->plan] ?? $this->plan;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Checkout non completato',
            'trial' => 'In prova',
            'paying' => 'Rate in corso',
            'past_due' => 'Rata non pagata',
            'completed' => 'Pagato per intero',
            'canceled' => 'Annullato',
            default => $this->status,
        };
    }

    public function dialNumber(): string
    {
        return (new SiteLead(['phone' => $this->phone]))->dialNumber();
    }

    public static function euro(int $cents): string
    {
        return number_format($cents / 100, $cents % 100 ? 2 : 0, ',', '.');
    }
}
