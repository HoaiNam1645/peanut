<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Debit extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'remaining_debit',
        'debit_used',
        'status',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_debit' => 'decimal:2',
        'debit_used' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
