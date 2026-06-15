<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerStore extends Model
{
    protected $fillable = [
        'name',
        'code',
        'user_id',
        'total_order',
        'status',
        'account_no',
        'partner_app_id',
        'token',
        'api_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partnerApp(): BelongsTo
    {
        return $this->belongsTo(PartnerApp::class);
    }
}
