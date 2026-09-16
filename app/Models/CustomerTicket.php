<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerTicket extends Model
{
    protected $fillable = ['tenant_id', 'promo_id', 'name', 'email', 'phone', 'message', 'status'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function isNew(): bool
    {
        return $this->status === 'new';
    }
}
