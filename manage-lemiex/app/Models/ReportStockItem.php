<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportStockItem extends Model
{
    protected $fillable = [
        'report_stock_id',
        'variant_id',
        'stock',
        'last_week_sale',
        'status',
    ];

    public function reportStock(): BelongsTo
    {
        return $this->belongsTo(ReportStock::class);
    }
}
