<?php

namespace App\Models;

use App\Enums\ProductCategoryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'style',
        'status',
        'category_type',
        'mockup',
        'template_url',
        'brand',
        'warehouse_name',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function scopeWood($query)
    {
        return $query->where('category_type', ProductCategoryType::WOOD);
    }

    public function isWood(): bool
    {
        return $this->category_type === ProductCategoryType::WOOD;
    }
}
