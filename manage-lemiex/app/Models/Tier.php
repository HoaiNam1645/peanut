<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tier extends Model
{
    protected $fillable = [
        'tier_id',
        'name',
    ];

    protected $casts = [
        'tier_id' => 'integer',
    ];

    public function extraFees(): HasMany
    {
        return $this->hasMany(ExtraFee::class, 'tier_id', 'tier_id');
    }

    public function refundFees(): HasMany
    {
        return $this->hasMany(RefundFee::class, 'tier_id', 'tier_id');
    }

    public function embroideryFees(): HasMany
    {
        return $this->hasMany(EmbroideryFee::class, 'tier_id', 'tier_id');
    }

    public function priorityFees(): HasMany
    {
        return $this->hasMany(FulfillmentPriority::class, 'tier_id', 'tier_id')->where('active', true);
    }
}
