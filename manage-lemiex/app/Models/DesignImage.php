<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignImage extends Model
{
    protected $fillable = [
        'design_id',
        'image_path',
        'image_name',
    ];

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
