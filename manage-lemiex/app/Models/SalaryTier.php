<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hourly_rate',
        'currency',
        'description',
    ];
}
