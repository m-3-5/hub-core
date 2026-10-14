<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un post esterno (Facebook…) già trasformato in bozza di promo: serve a non importarlo due volte. */
class PromoSource extends Model
{
    protected $fillable = ['tenant_id', 'source', 'external_id', 'promo_id', 'permalink'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }
}
