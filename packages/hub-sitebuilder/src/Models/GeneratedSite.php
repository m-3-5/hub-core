<?php

namespace M35\HubSitebuilder\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedSite extends Model
{
    protected $fillable = [
        'tenant_id',
        'status',
        'answers',
        'hero_svg_path',
        'hero_video_path',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'published_at' => 'datetime',
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
}
