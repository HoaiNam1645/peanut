<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tracking extends Model
{
    protected $table = 'tracking';

    protected $fillable = [
        'tracking_id',
        'order_id',
        'status',
        'service',
        'method',
        'total_day',
        'ssk',
        'update_time',
    ];

    protected $casts = [
        'update_time' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
