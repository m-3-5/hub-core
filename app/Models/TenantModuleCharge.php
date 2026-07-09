<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModuleCharge extends Model
{
    protected $fillable = [
        'tenant_id',
        'module',
        'charge_type',
        'period',
        'description',
        'amount_cents',
        'paid',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function amountEuros(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', '.');
    }
}
