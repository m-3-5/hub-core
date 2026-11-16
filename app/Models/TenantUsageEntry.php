<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageEntry extends Model
{
    protected $fillable = ['tenant_id', 'amount_cents', 'reason', 'status', 'item_type', 'item_id', 'bill_after', 'billed_at', 'payment_intent_id', 'description', 'user_id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'item_id' => 'integer', 'bill_after' => 'datetime', 'billed_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
