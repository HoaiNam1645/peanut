<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'verify_code',
        'check_time',
        'created_at',
    ];

    protected $casts = [
        'check_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
