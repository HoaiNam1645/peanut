<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'avatar',
        'address',
        'country_id',
        'birthday',
        'two_factor_country_code',
        'two_factor_phone',
        'two_factor_options',
        'wallet_balance',
        'private_seller',
        'webhook_url',
        'telegram_id',
        'is_support_us',
        'max_debit',
        'max_date_debit',
        'min_date_debit',
        'debit_status',
        'production',
    ];

    protected $casts = [
        'birthday' => 'date',
        'wallet_balance' => 'decimal:2',
        'max_debit' => 'decimal:2',
        'is_support_us' => 'boolean',
        'debit_status' => 'boolean',
        'production' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class, 'private_seller', 'tier_id');
    }
}
