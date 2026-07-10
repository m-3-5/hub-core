<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'context_type',
        'context_id',
        'context_label',
        'message',
        'status',
        'response',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
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

    public function isAnswered(): bool
    {
        return $this->status === 'answered';
    }

    public function hoursOld(): int
    {
        return (int) $this->created_at->diffInHours(now());
    }

    public function isPastSla(): bool
    {
        return ! $this->isAnswered() && $this->hoursOld() >= 24;
    }
}
