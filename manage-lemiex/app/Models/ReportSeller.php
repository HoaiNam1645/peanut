<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSeller extends Model
{
    protected $fillable = [
        'seller_id',
        'date',
        'total_orders',
        'total_revenue',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
    ];
}
