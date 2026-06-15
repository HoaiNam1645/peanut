<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemMeta extends Model
{
    protected $fillable = [
        'order_item_id',
        'meta_key',
        'meta_value',
        'append_qr_design',
        'overide_qr',
        'oversize_site',
        'switch',
        'status',
        'update_time',
        'embroidery_type',
    ];

    protected $casts = [
        'append_qr_design' => 'boolean',
        'overide_qr' => 'boolean',
        'switch' => 'integer', // Changed from boolean to integer to store stitch_count
        'status' => 'boolean',
        'update_time' => 'datetime',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
