<?php

namespace M35\HubPayments\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableOrder extends Model
{
    protected $fillable = [
        'tenant_id', 'channel', 'status', 'stripe_session_id', 'items', 'currency', 'amount_cents',
        'customer_email', 'customer_name', 'customer_phone', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
