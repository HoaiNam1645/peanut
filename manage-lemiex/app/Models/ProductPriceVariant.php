<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceVariant extends Model
{
    protected $fillable = [
        'product_variant_id',
        'tier_id',
        'type',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
