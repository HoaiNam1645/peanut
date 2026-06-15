<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timeline extends Model
{
    protected $table = 'timeline';

    protected $fillable = [
        'object',
        'object_id',
        'owner_id',
        'action',
        'note',
    ];
}
