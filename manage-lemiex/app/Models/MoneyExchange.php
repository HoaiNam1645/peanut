<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyExchange extends Model
{
    protected $table = 'money_exchange';

    protected $fillable = [
        'group_id',
        'group_name',
        'get_amount',
        'pay_amount',
        'rate',
        'vnd',
        'transaction_id',
        'type',
    ];

    protected $casts = [
        'get_amount' => 'decimal:2',
        'pay_amount' => 'decimal:2',
    ];
}
