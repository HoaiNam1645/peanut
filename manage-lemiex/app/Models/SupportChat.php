<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChat extends Model
{
    protected $fillable = [
        'support_id',
        'user_id',
        'message',
    ];

    public function support(): BelongsTo
    {
        return $this->belongsTo(Support::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
