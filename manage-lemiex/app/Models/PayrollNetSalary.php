<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollNetSalary extends Model
{
    protected $fillable = [
        'employee_id',
        'period',
        'net_salary',
        'company_tax',
        'note',
    ];

    protected $casts = [
        'net_salary' => 'float',
        'company_tax' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
