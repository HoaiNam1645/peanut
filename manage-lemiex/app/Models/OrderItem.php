<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'variant_id',
        'product_name',
        'quantity',
        'price',
        'status',
        'mockup',
        'mockup_back',
        'pdf',
        'sides',
        'id_style',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function metas(): HasMany
    {
        return $this->hasMany(OrderItemMeta::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class, 'item_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'variant_id');
    }

    /**
     * Get product through variant relationship
     * Uses hasManyThrough pattern
     */
    public function product()
    {
        return $this->hasOneThrough(
            Product::class,
            ProductVariant::class,
            'variant_id', // Foreign key on ProductVariant table
            'id', // Foreign key on Product table
            'variant_id', // Local key on OrderItem table
            'product_id' // Local key on ProductVariant table
        );
    }

    /**
     * Get workflows for this order item
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(OrderItemWorkflow::class);
    }
}
