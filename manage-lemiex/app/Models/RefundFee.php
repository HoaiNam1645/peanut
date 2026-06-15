<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundFee extends Model
{
    protected $table = 'refund_fee';

    protected $fillable = [
        'tier_id',
        'stitch',
        'amount',
    ];

    protected $casts = [
        'stitch' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'tier_id', 'tier_id');
    }
}
