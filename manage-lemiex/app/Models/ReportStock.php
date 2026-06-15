<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportStock extends Model
{
    protected $fillable = [
        'report_date',
    ];

    protected $casts = [
        'report_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReportStockItem::class);
    }
}
