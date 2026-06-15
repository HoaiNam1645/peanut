<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignItem extends Model
{
    protected $fillable = [
        'design_id',
        'file_path',
        'file_name',
        'file_type',
        'type',
    ];

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
