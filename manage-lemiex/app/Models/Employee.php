<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'device_id',
        'user_id',
        'user_name',
    ];

    public function timeLogs()
    {
        return $this->hasMany(TimeLog::class);
    }

    public function salaryHistory()
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function salaryAdjustments()
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    /**
     * Get the current active salary
     */
    public function currentSalary()
    {
        return $this->hasOne(EmployeeSalary::class)
            ->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc');
    }
}
