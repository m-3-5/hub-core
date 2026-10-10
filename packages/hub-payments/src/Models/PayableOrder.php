<?php

namespace M35\HubPayments\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableOrder extends Model
{
    protected $fillable = [
        'tenant_id', 'channel', 'flow', 'status', 'payout_status', 'connect_account_id',
        'stripe_session_id', 'stripe_payment_intent_id', 'items', 'currency', 'amount_cents',
        'commission_cents', 'commission_charge_id',
        'customer_email', 'customer_name', 'customer_phone', 'paid_at', 'release_at', 'buyer_token',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'paid_at' => 'datetime',
            'release_at' => 'datetime',
        ];
    }

    public function isProtected(): bool
    {
        return $this->flow === 'protected';
    }

    /** Pagato ma i soldi sono ancora trattenuti da Hub Core (non ancora girati al venditore). */
    public function isHeld(): bool
    {
        return $this->isProtected() && $this->payout_status === 'held';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
