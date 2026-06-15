<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollNetSalary;
use App\Models\SalaryAdjustment;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    /**
     * Calculate payroll for a specific month or custom date range
     */
    public function calculateMonthlyPayroll($month, $employeeId = null, $dateFrom = null, $dateTo = null)
    {
        // If custom date range provided, use it; otherwise fall back to month
        if ($dateFrom && $dateTo) {
            $startDate = Carbon::parse($dateFrom)->startOfDay();
            $endDate = Carbon::parse($dateTo)->endOfDay();
            $periodLabel = $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d');
        } else {
            $date = Carbon::parse($month);
            $startDate = $date->copy()->startOfMonth();
            $endDate = $date->copy()->endOfMonth();
            $periodLabel = $date->format('Y-m');
        }

        // 1. Get Employees
        $employeesQuery = Employee::query();
        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }
        $employees = $employeesQuery->get();

        $payrollData = [];

        foreach ($employees as $employee) {
            $timeLogs = TimeLog::where('employee_id', $employee->id)
                ->whereBetween('check_time', [$startDate->format('Y-m-d 00:00:00'), $endDate->format('Y-m-d 23:59:59')])
                ->get()
                ->groupBy(function ($log) {
                    return Carbon::parse($log->check_time)->format('Y-m-d');
                });

            // Skip if no work logs and no adjustments? 
            // Maybe we still want to show them if they have adjustments but no work logs?
            // For now, let's process everyone.

            $totalHours = 0;
            $totalBaseSalary = 0;
            $dailyDetails = [];

            // 3. Process each day in the month (or only days worked)
            // Iterate through days worked found in logs
            foreach ($timeLogs as $dateStr => $logs) {
                $workDate = Carbon::parse($dateStr);

                // Calculate hours worked in this day
                $secondsWorked = $this->calculateSecondsWorked($logs);
                $hoursWorked = round($secondsWorked / 3600, 2);

                if ($hoursWorked <= 0) continue;

                // 4. Find Applicable Salary Rate for this specific day
                $rate = $this->getRateForDate($employee->id, $workDate);

                // Don't round intermediate calculations to avoid accumulating rounding errors
                $dailyPay = $hoursWorked * $rate;

                $totalHours += $hoursWorked;
                $totalBaseSalary += $dailyPay;

                // Only round for display in daily details
                $dailyDetails[] = [
                    'date' => $dateStr,
                    'hours' => round($hoursWorked, 2),
                    'rate' => $rate,
                    'pay' => round($dailyPay, 2) // Round only for display
                ];
            }

            // 5. Get Adjustments (Bonus/Penalty)
            $adjustments = SalaryAdjustment::where('employee_id', $employee->id)
                ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get();

            // Calculate based on amount sign: > 0 is Addition, < 0 is Deduction
            $additions = $adjustments->where('amount', '>', 0)->sum('amount');
            $deductions = $adjustments->where('amount', '<', 0)->sum('amount'); // This will be negative number

            // Final Calculation: Base + Additions + Deductions (which is negative)
            $finalSalary = $totalBaseSalary + $adjustments->sum('amount');

            // For display purpose, make deductions positive string
            $displayDeductions = abs($deductions);

            // Get current hourly rate for display
            $currentRate = $this->getRateForDate($employee->id, Carbon::now());

            // Gross salary = base + adjustments (the calculated value)
            $grossSalary = round($finalSalary, 2);

            $payrollData[] = [
                'employee_id' => $employee->id,
                'user_id' => $employee->user_id,
                'user_name' => $employee->user_name,
                'current_rate' => $currentRate,
                'month' => $periodLabel,
                'total_hours' => round($totalHours, 2),
                'base_salary' => round($totalBaseSalary, 2),
                'additions' => $additions,
                'deductions' => $displayDeductions,
                'gross_salary' => $grossSalary,
                'final_salary' => $grossSalary, // Keep for backward compat
                'net_salary' => 0, // Will be filled below
                'company_tax' => 0, // Will be filled below
                'adjustments_detail' => $adjustments,
            ];
        }

        // Batch-load net salaries for all employees in this period
        $employeeIds = array_column($payrollData, 'employee_id');
        $netSalaries = PayrollNetSalary::whereIn('employee_id', $employeeIds)
            ->where('period', $periodLabel)
            ->get()
            ->keyBy('employee_id');

        foreach ($payrollData as &$row) {
            $netRecord = $netSalaries->get($row['employee_id']);
            $row['net_salary'] = $netRecord ? round($netRecord->net_salary, 2) : 0;
            $row['company_tax'] = $netRecord ? round($netRecord->company_tax, 2) : 0;
            $row['net_salary_note'] = $netRecord ? $netRecord->note : null;
        }
        unset($row);

        return $payrollData;
    }

    /**
     * Get applicable hourly rate for a specific date
     */
    private function getRateForDate($employeeId, Carbon $date)
    {
        // Find the most recent active salary record effective <= date
        $salary = EmployeeSalary::with('tier')
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->where('effective_date', '<=', $date->format('Y-m-d'))
            ->orderBy('effective_date', 'desc')
            ->first();

        if (!$salary) {
            return 0; // No salary config found
        }

        return $salary->hourly_rate; // Attribute accessor handles custom vs tier
    }

    /**
     * Calculate seconds worked from a collection of logs for one day
     * Logic: Min In -> Max Out
     * Warning: This logic assumes continuous work. Better logic would map pairs of In/Out.
     */
    private function calculateSecondsWorked($logs)
    {
        if ($logs->isEmpty()) return 0;

        $checkIn = $logs->min('check_time');
        $checkOut = $logs->max('check_time');

        if ($checkIn && $checkOut && $checkIn !== $checkOut) {
            $in = Carbon::parse($checkIn);
            $out = Carbon::parse($checkOut);

            // 9:00 AM Rule: If check-in is before 09:00, count from 09:00
            $startLimit = $in->copy()->setTime(9, 0, 0);
            if ($in->lt($startLimit)) {
                $in = $startLimit;
            }

            // If check-out is before adjusted check-in, return 0
            if ($out->lte($in)) {
                return 0;
            }

            return abs($in->diffInSeconds($out));
        }

        return 0;
    }
}
