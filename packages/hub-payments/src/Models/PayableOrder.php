<?php

namespace M35\HubPayments\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableOrder extends Model
{
    protected $fillable = [
        'tenant_id', 'channel', 'flow', 'status', 'payout_status', 'connect_account_id',
        'stripe_session_id', 'stripe_payment_intent_id', 'stripe_charge_id', 'stripe_transfer_id', 'stripe_refund_id',
        'items', 'currency', 'amount_cents', 'commission_cents', 'commission_charge_id', 'stripe_fee_cents', 'payout_cents',
        'customer_email', 'customer_name', 'customer_phone', 'paid_at', 'release_at', 'confirmed_at', 'released_at',
        'problem_reported_at', 'problem_message', 'refunded_at', 'buyer_token',
        'buyer_terms_version', 'buyer_terms_accepted_at', 'buyer_terms_ip',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'paid_at' => 'datetime',
            'release_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'released_at' => 'datetime',
            'problem_reported_at' => 'datetime',
            'refunded_at' => 'datetime',
            'buyer_terms_accepted_at' => 'datetime',
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

    public function isFrozen(): bool
    {
        return $this->isProtected() && $this->payout_status === 'frozen';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
