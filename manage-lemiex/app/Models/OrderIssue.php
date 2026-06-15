<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIssue extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'severity',
        'info_error',
        'telegram_chat_id',
        'telegram_message_id',
        'notified_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'info_error' => 'array',
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_IGNORED = 'ignored';

    const SEVERITY_CRITICAL = 'critical';
    const SEVERITY_WARN = 'warn';
    const SEVERITY_INFO = 'info';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
