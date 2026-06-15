<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Models\EmployeeSalary;
use App\Models\SalaryAdjustment;
use App\Models\SalaryTier;
use App\Services\PayrollService;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    // --- SALARY TIERS ---

    public function getTiers()
    {
        $tiers = SalaryTier::all();
        return response()->json(['status' => true, 'data' => $tiers], HttpCode::SUCCESS);
    }

    public function createTier(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'hourly_rate' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
        ]);

        $tier = SalaryTier::create($request->all());
        return response()->json(['status' => true, 'data' => $tier, 'message' => 'Tier created'], HttpCode::SUCCESS);
    }

    public function updateTier(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'hourly_rate' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
        ]);

        $tier = SalaryTier::findOrFail($id);
        $tier->update($request->all());
        return response()->json(['status' => true, 'data' => $tier, 'message' => 'Tier updated'], HttpCode::SUCCESS);
    }

    public function deleteTier($id)
    {
        $tier = SalaryTier::findOrFail($id);
        $tier->delete();
        return response()->json(['status' => true, 'message' => 'Tier deleted'], HttpCode::SUCCESS);
    }

    // --- EMPLOYEE SALARY ASSIGNMENT ---

    public function getSalaryHistory($employeeId)
    {
        $history = EmployeeSalary::with('tier')
            ->where('employee_id', $employeeId)
            ->orderBy('effective_date', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $history], HttpCode::SUCCESS);
    }

    public function getCurrentSalary($employeeId)
    {
        $salary = EmployeeSalary::with('tier')
            ->where('employee_id', $employeeId)
            ->where('is_active', true)
            ->orderBy('effective_date', 'desc')
            ->first();

        return response()->json(['status' => true, 'data' => $salary], HttpCode::SUCCESS);
    }

    /**
     * Get salary log including soft deleted records
     */
    public function getSalaryLog($employeeId)
    {
        $log = EmployeeSalary::withTrashed()
            ->with('tier')
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'data' => $log], HttpCode::SUCCESS);
    }

    /**
     * Create new salary (soft deletes old one)
     */
    public function createSalary(Request $request, $employeeId)
    {
        $request->validate([
            'salary_tier_id' => 'nullable|exists:salary_tiers,id',
            'custom_hourly_rate' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        if (!$request->salary_tier_id && !$request->custom_hourly_rate) {
            return response()->json([
                'status' => false,
                'message' => 'Either tier or custom rate must be provided'
            ], HttpCode::BAD_REQUEST);
        }

        // Soft delete all existing active salaries for this employee
        EmployeeSalary::where('employee_id', $employeeId)
            ->where('is_active', true)
            ->delete();

        // Create new salary record
        $salary = EmployeeSalary::create([
            'employee_id' => $employeeId,
            'salary_tier_id' => $request->salary_tier_id,
            'custom_hourly_rate' => $request->custom_hourly_rate,
            'effective_date' => $request->effective_date,
            'is_active' => true,
            'note' => $request->note,
        ]);

        return response()->json(['status' => true, 'data' => $salary->load('tier'), 'message' => 'Salary created'], HttpCode::SUCCESS);
    }

    /**
     * Update existing salary record
     */
    public function updateSalary(Request $request, $employeeId)
    {
        $request->validate([
            'salary_tier_id' => 'nullable|exists:salary_tiers,id',
            'custom_hourly_rate' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        if (!$request->salary_tier_id && !$request->custom_hourly_rate) {
            return response()->json([
                'status' => false,
                'message' => 'Either tier or custom rate must be provided'
            ], HttpCode::BAD_REQUEST);
        }

        $salary = EmployeeSalary::where('employee_id', $employeeId)
            ->where('is_active', true)
            ->firstOrFail();

        $salary->update([
            'salary_tier_id' => $request->salary_tier_id,
            'custom_hourly_rate' => $request->custom_hourly_rate,
            'effective_date' => $request->effective_date,
            'note' => $request->note,
        ]);

        return response()->json(['status' => true, 'data' => $salary->load('tier'), 'message' => 'Salary updated'], HttpCode::SUCCESS);
    }

    // --- ADJUSTMENTS ---

    public function getAdjustments(Request $request)
    {
        $query = SalaryAdjustment::query()->with('employee');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('month')) {
            $query->where('date', 'like', $request->month . '%');
        }

        $data = $query->orderBy('date', 'desc')->get();
        return response()->json(['status' => true, 'data' => $data], HttpCode::SUCCESS);
    }

    public function createAdjustment(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|string|max:50',
            'amount' => 'required|numeric', // Allow negative for deductions
            'date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        $adj = SalaryAdjustment::create($request->all());
        return response()->json(['status' => true, 'data' => $adj, 'message' => 'Adjustment created'], HttpCode::SUCCESS);
    }

    public function updateAdjustment(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|string|max:50',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'reason' => 'nullable|string',
        ]);

        $adjustment = SalaryAdjustment::findOrFail($id);
        $adjustment->update($request->only([
            'type',
            'amount',
            'date',
            'reason',
        ]));

        return response()->json([
            'status' => true,
            'data' => $adjustment->fresh(),
            'message' => 'Adjustment updated',
        ], HttpCode::SUCCESS);
    }

    public function deleteAdjustment($id)
    {
        SalaryAdjustment::destroy($id);
        return response()->json(['status' => true, 'message' => 'Adjustment deleted'], HttpCode::SUCCESS);
    }

    // --- PAYROLL REPORT ---

    public function getPayrollReport(Request $request)
    {
        // Validate: either month OR (date_from AND date_to) is required
        $request->validate([
            'month' => 'required_without_all:date_from,date_to|date_format:Y-m',
            'date_from' => 'required_without:month|date',
            'date_to' => 'required_with:date_from|date|after_or_equal:date_from',
        ]);

        $data = $this->payrollService->calculateMonthlyPayroll(
            $request->month,
            $request->employee_id,
            $request->date_from,
            $request->date_to
        );

        return response()->json(['status' => true, 'data' => $data], HttpCode::SUCCESS);
    }

    // --- NET SALARY ---

    /**
     * Update net salary / company tax for an employee in a specific period
     */
    public function updateNetSalary(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period' => 'required|string',
            'net_salary' => 'nullable|numeric|min:0',
            'company_tax' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $updateData = [];
        if ($request->has('net_salary')) {
            $updateData['net_salary'] = $request->net_salary;
        }
        if ($request->has('company_tax')) {
            $updateData['company_tax'] = $request->company_tax;
        }
        if ($request->has('note')) {
            $updateData['note'] = $request->note;
        }

        $record = \App\Models\PayrollNetSalary::updateOrCreate(
            [
                'employee_id' => $request->employee_id,
                'period' => $request->period,
            ],
            $updateData
        );

        return response()->json([
            'status' => true,
            'data' => $record,
            'message' => 'Payroll record updated'
        ], HttpCode::SUCCESS);
    }
}
