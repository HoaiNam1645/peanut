<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraFee extends Model
{
    protected $table = 'extra_fee';

    protected $fillable = [
        'tier_id',
        'min_stitch',
        'max_stitch',
        'amount',
    ];

    protected $casts = [
        'min_stitch' => 'integer',
        'max_stitch' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'tier_id', 'tier_id');
    }
}
