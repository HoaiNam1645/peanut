<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeSalary extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'salary_tier_id',
        'custom_hourly_rate',
        'effective_date',
        'is_active',
        'note',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_active' => 'boolean',
        'custom_hourly_rate' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function tier()
    {
        return $this->belongsTo(SalaryTier::class, 'salary_tier_id');
    }

    /**
     * Get the actual hourly rate (tier or custom)
     */
    public function getHourlyRateAttribute()
    {
        if ($this->custom_hourly_rate) {
            return $this->custom_hourly_rate;
        }

        return $this->tier ? $this->tier->hourly_rate : 0;
    }
}
