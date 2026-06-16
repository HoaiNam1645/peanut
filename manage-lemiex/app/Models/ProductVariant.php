<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'variant_id',
        'sku',
        'style',
        'color',
        'size',
        'stock',
        'pending_demand',
        'active',
        'weight',
        'length',
        'width',
        'height',
        'supplier_price',
        // Garment size-chart measurements (inch + cm)
        'chest_inch',
        'chest_cm',
        'length_inch',
        'length_cm',
        'neck_inch',
        'neck_cm',
    ];

    protected $casts = [
        'active' => 'boolean',
        'supplier_price' => 'decimal:2',
        'chest_inch' => 'decimal:2',
        'chest_cm' => 'decimal:2',
        'length_inch' => 'decimal:2',
        'length_cm' => 'decimal:2',
        'neck_inch' => 'decimal:2',
        'neck_cm' => 'decimal:2',
    ];

    // Appends for API responses (computed in service layer to avoid N+1)
    // protected $appends = ['reserved', 'available'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceVariants(): HasMany
    {
        return $this->hasMany(ProductPriceVariant::class, 'product_variant_id', 'variant_id');
    }

    public function stockAuditLogs(): HasMany
    {
        return $this->hasMany(StockAuditLog::class, 'product_variant_id', 'variant_id');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class, 'product_variant_id', 'variant_id');
    }

    /**
     * Note: reserved and available are computed in StockService to avoid N+1 queries
     * They are added dynamically to the model when needed
     */
}
