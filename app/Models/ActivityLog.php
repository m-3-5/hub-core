<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'input',
        'output',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(Tenant $tenant, string $event, array $input = [], array $output = [], ?Model $subject = null): self
    {
        return self::create([
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'input' => $input,
            'output' => $output,
        ]);
    }
}
