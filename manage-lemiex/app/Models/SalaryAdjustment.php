<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'type', // bonus, penalty, kpi, advance
        'amount',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
