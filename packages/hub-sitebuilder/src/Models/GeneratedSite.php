<?php

namespace M35\HubSitebuilder\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GeneratedSite extends Model
{
    /** Giorni di validità del link segreto inviato per email. */
    public const EDIT_LINK_DAYS = 90;

    protected $fillable = [
        'tenant_id',
        'status',
        'answers',
        'hero_svg_path',
        'hero_video_path',
        'published_at',
        'edit_token_hash',
        'edit_link_sent_at',
        'last_edited_at',
        'warned_at',
        'delete_after',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'published_at' => 'datetime',
            'edit_link_sent_at' => 'datetime',
            'last_edited_at' => 'datetime',
            'warned_at' => 'datetime',
            'delete_after' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isReady(): bool
    {
        return in_array($this->status, ['ready', 'published'], true);
    }

    /** Crea un nuovo link segreto (il precedente smette di funzionare). Restituisce il testo da mettere nell'email: non viene salvato. */
    public function issueEditToken(): string
    {
        $token = Str::random(48);

        $this->forceFill([
            'edit_token_hash' => hash('sha256', $token),
            'edit_link_sent_at' => now(),
        ])->save();

        return $token;
    }

    public static function findByEditToken(string $token): ?self
    {
        if (strlen($token) < 40) {
            return null;
        }

        $site = static::query()->where('edit_token_hash', hash('sha256', $token))->first();

        if (! $site || ! $site->edit_link_sent_at || $site->edit_link_sent_at->lt(now()->subDays(self::EDIT_LINK_DAYS))) {
            return null;
        }

        return $site;
    }

    /** Il sito è visibile al pubblico solo se pubblicato E l'attività ha confermato una email vera. */
    public function isLive(): bool
    {
        return $this->isPublished() && $this->tenant && ! $this->tenant->isGuestPending();
    }
}
