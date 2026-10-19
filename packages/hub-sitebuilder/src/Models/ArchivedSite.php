<?php

namespace M35\HubSitebuilder\Models;

use Illuminate\Database\Eloquent\Model;

/** Sito web rimosso e conservato per l'amministratore (non è più raggiungibile dal pubblico). */
class ArchivedSite extends Model
{
    protected $fillable = [
        'tenant_id', 'tenant_name', 'tenant_slug', 'owner_email', 'answers', 'reason', 'reason_label', 'site_created_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['answers' => 'array', 'site_created_at' => 'datetime', 'archived_at' => 'datetime'];
    }
}
