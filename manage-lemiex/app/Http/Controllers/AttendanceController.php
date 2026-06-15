<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\ResponseMessage;
use App\Models\Employee;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    private function formatDuration(int $seconds): string
    {
        $seconds = abs($seconds);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    private function transformAttendanceLog(object $log): object
    {
        $log->scan_count = (int) ($log->scan_count ?? 0);

        if (!$log->check_in || !$log->check_out || $log->check_in === $log->check_out) {
            $log->total_work = '00:00:00';
            $log->is_missing_pair = true;
            return $log;
        }

        $checkIn = Carbon::parse($log->check_in);
        $checkOut = Carbon::parse($log->check_out);

        $startLimit = $checkIn->copy()->setTime(9, 0, 0);
        if ($checkIn->lt($startLimit)) {
            $checkIn = $startLimit;
        }

        if ($checkOut->lte($checkIn)) {
            $log->total_work = '00:00:00';
            $log->is_missing_pair = true;
            return $log;
        }

        $diffSeconds = abs($checkIn->diffInSeconds($checkOut));
        $log->total_work = $this->formatDuration($diffSeconds);
        $log->is_missing_pair = false;

        return $log;
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $handle = fopen($file->getRealPath(), "r");

            // Read header
            $headerLine = fgets($handle);
            $headers = preg_split('/\s+/', trim($headerLine));

            $requiredHeaders = ['No', 'DevId', 'UserId', 'UName', 'Verify', 'DateTime'];

            if ($headers !== $requiredHeaders) {
                $missing = array_diff($requiredHeaders, $headers);
                $extra   = array_diff($headers, $requiredHeaders);

                $messages = [];

                if (!empty($missing)) {
                    $messages[] = 'Missing columns: ' . implode(', ', $missing);
                }

                if (!empty($extra)) {
                    $messages[] = 'Extra columns: ' . implode(', ', $extra);
                }

                throw new \Exception(implode(' | ', $messages));
            }


            while (($line = fgets($handle)) !== false) {

                $values = preg_split('/\s+/', trim($line));

                // Gộp Date + Time
                if (count($values) > count($headers)) {
                    $values[5] = $values[5] . ' ' . $values[6];
                    unset($values[6]);
                    $values = array_values($values);
                }

                $dataFile = array_combine($headers, $values);

                $employee = Employee::firstOrCreate(
                    [
                        'device_id' => $dataFile['DevId'],
                        'user_id' => $dataFile['UserId'],
                    ],
                    ['user_name' => trim($dataFile['UName'])]
                );

                $employee->timeLogs()->firstOrCreate(
                    ['check_time' => Carbon::parse($dataFile['DateTime'])],
                    ['verify_code' => $dataFile['Verify']]
                );
            }

            fclose($handle);
            DB::commit();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Import success',
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Import failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get paginated list of employees with their attendance summary
     * Logs are NOT included here - use getLogs() to fetch them separately
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);

            // Determine filter range and anchor date
            $anchorDate = Carbon::now();
            $filterStart = null;
            $filterEnd = null;

            if ($request->has('date_from') && $request->has('date_to')) {
                $start = Carbon::parse($request->date_from);
                $end = Carbon::parse($request->date_to);

                $filterStart = $start->copy()->startOfDay()->format('Y-m-d 00:00:00');
                $filterEnd = $end->copy()->endOfDay()->format('Y-m-d 23:59:59');

                // If filtering by custom range, we'll use the anchorDate from End Date to determine context if needed,
                // but crucially, we will OVERRIDE the Month Context to use this custom range
                $anchorDate = $end;
            } elseif ($request->has('month')) {
                $anchorDate = Carbon::parse($request->month);
                $filterStart = $anchorDate->copy()->startOfMonth()->format('Y-m-d 00:00:00');
                $filterEnd = $anchorDate->copy()->endOfMonth()->format('Y-m-d 23:59:59');
            } elseif ($request->has('date')) {
                $anchorDate = Carbon::parse($request->date);
                $filterStart = $anchorDate->copy()->startOfDay()->format('Y-m-d 00:00:00');
                $filterEnd = $anchorDate->copy()->endOfDay()->format('Y-m-d 23:59:59');
            } elseif ($request->has('week')) {
                $anchorDate = Carbon::parse($request->week);
                $filterStart = $anchorDate->copy()->startOfWeek()->format('Y-m-d 00:00:00');
                $filterEnd = $anchorDate->copy()->endOfWeek()->format('Y-m-d 23:59:59');
            } else {
                // If no filter, defaulting to showing all employees who have EVER worked? 
                // Or maybe default to current Month? 
                // Let's stick to current logic: Get employees who have logs based on default anchor (Current Month) to avoid loading too much data?
                // Or better: If no filter, show ALL time summary?
                // For performance, let's filter by current month by default if no params
                // But previously we fetched ALL. Let's fetch ALL for now to match previous behavior if no filter.
                $filterStart = null;
                $filterEnd = null;
            }

            // Get employees who have time logs within the filter range
            $employeesQuery = Employee::select('id', 'user_id', 'user_name');

            if ($filterStart && $filterEnd) {
                $employeesQuery->whereHas('timeLogs', function ($q) use ($filterStart, $filterEnd) {
                    $q->whereBetween('check_time', [$filterStart, $filterEnd]);
                });
            } else {
                $employeesQuery->whereHas('timeLogs');
            }

            $employees = $employeesQuery->get();

            // Calculate context ranges based on the anchor date
            $weekStart = $anchorDate->copy()->startOfWeek()->format('Y-m-d 00:00:00');
            $weekEnd = $anchorDate->copy()->endOfWeek()->format('Y-m-d 23:59:59');
            $monthStart = $anchorDate->copy()->startOfMonth()->format('Y-m-d 00:00:00');
            $monthEnd = $anchorDate->copy()->endOfMonth()->format('Y-m-d 23:59:59');

            // Override Month Column Calculation if using Custom Range
            if ($request->has('date_from') && $request->has('date_to')) {
                $monthStart = $filterStart;
                $monthEnd = $filterEnd;
            }
            $yearStart = $anchorDate->copy()->startOfYear()->format('Y-m-d 00:00:00');
            $yearEnd = $anchorDate->copy()->endOfYear()->format('Y-m-d 23:59:59');

            // Build employee summary with total hours
            $employeeSummary = $employees->map(function ($employee) use ($weekStart, $weekEnd, $monthStart, $monthEnd, $yearStart, $yearEnd, $filterStart, $filterEnd) {
                // Helper to calculate total work hours for a date range
                $calculateTotalHours = function ($startDate, $endDate) use ($employee) {
                    $logs = TimeLog::where('employee_id', $employee->id)
                        ->whereBetween('check_time', [$startDate, $endDate])
                        ->select(
                            DB::raw('DATE(check_time) as work_date'),
                            DB::raw('MIN(check_time) as check_in'),
                            DB::raw('MAX(check_time) as check_out')
                        )
                        ->groupBy('work_date')
                        ->get();

                    $totalSeconds = 0;
                    foreach ($logs as $log) {
                        if ($log->check_in && $log->check_out && $log->check_in !== $log->check_out) {
                            $checkIn = Carbon::parse($log->check_in);
                            $checkOut = Carbon::parse($log->check_out);

                            // Rule: Count hours starting from 09:00:00
                            $startLimit = $checkIn->copy()->setTime(9, 0, 0);

                            // If check-in is before 09:00, count from 09:00
                            if ($checkIn->lt($startLimit)) {
                                $checkIn = $startLimit;
                            }

                            // If check-out is before the adjusted check-in (e.g. worked 8:00-8:30), 0 hours
                            if ($checkOut->lte($checkIn)) {
                                continue;
                            }

                            // Ensure positive difference
                            $diff = abs($checkIn->diffInSeconds($checkOut));
                            $totalSeconds += $diff;
                        }
                    }

                    return $totalSeconds;
                };

                // Calculate hours for each context period
                $weekSeconds = $calculateTotalHours($weekStart, $weekEnd);
                $monthSeconds = $calculateTotalHours($monthStart, $monthEnd);
                $yearSeconds = $calculateTotalHours($yearStart, $yearEnd);

                // Count total days worked (Based on FILTER range, or All Time if no filter)
                $daysQuery = TimeLog::where('employee_id', $employee->id)
                    ->select(DB::raw('DATE(check_time) as work_date'))
                    ->groupBy('work_date');

                if ($filterStart && $filterEnd) {
                    $daysQuery->whereBetween('check_time', [$filterStart, $filterEnd]);
                }

                $totalDays = $daysQuery->get()->count();

                // Format seconds to HH:MM:SS
                return [
                    'user_id' => $employee->user_id,
                    'user_name' => $employee->user_name,
                    'total_days' => $totalDays,
                    'total_hours_week' => $this->formatDuration($weekSeconds),
                    'total_hours_month' => $this->formatDuration($monthSeconds),
                    'total_hours_year' => $this->formatDuration($yearSeconds),
                ];
            })->values();

            // Apply search/filter if provided
            if ($request->has('user_name')) {
                $searchName = strtolower($request->user_name);
                $employeeSummary = $employeeSummary->filter(function ($emp) use ($searchName) {
                    return str_contains(strtolower($emp['user_name']), $searchName);
                })->values();
            }

            if ($request->has('user_id')) {
                $searchId = $request->user_id;
                $employeeSummary = $employeeSummary->filter(function ($emp) use ($searchId) {
                    return $emp['user_id'] == $searchId;
                })->values();
            }

            // Paginate the employee list
            $total = $employeeSummary->count();
            $paginatedData = $employeeSummary->slice(($page - 1) * $perPage, $perPage)->values();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $paginatedData,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / $perPage),
                ],
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Error fetching data',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get attendance logs for a specific user (paginated)
     */
    public function getLogs(Request $request, $userId)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            $query = TimeLog::query()
                ->join('employees', 'employees.id', '=', 'time_logs.employee_id')
                ->where('employees.user_id', $userId)
                ->select([
                    DB::raw('DATE(time_logs.check_time) as work_date'),
                    DB::raw('MIN(time_logs.check_time) as check_in'),
                    DB::raw('MAX(time_logs.check_time) as check_out'),
                    DB::raw('COUNT(time_logs.id) as scan_count'),
                ])
                ->groupBy('work_date');

            // Apply date filters
            if ($request->has('date_from') && $request->has('date_to')) {
                $start = Carbon::parse($request->date_from)->startOfDay()->format('Y-m-d 00:00:00');
                $end = Carbon::parse($request->date_to)->endOfDay()->format('Y-m-d 23:59:59');
                $query->whereBetween('time_logs.check_time', [$start, $end]);
            }

            if ($request->has('date')) {
                $query->whereDate('time_logs.check_time', $request->date);
            }

            if ($request->has('month')) {
                $date = Carbon::parse($request->month);
                $query->whereYear('time_logs.check_time', $date->year)
                    ->whereMonth('time_logs.check_time', $date->month);
            }

            if ($request->has('week')) {
                $date = Carbon::parse($request->week);
                $query->whereBetween('time_logs.check_time', [
                    $date->startOfWeek()->format('Y-m-d 00:00:00'),
                    $date->endOfWeek()->format('Y-m-d 23:59:59')
                ]);
            }

            $query->orderBy('work_date', 'desc');

            // Get total count before pagination
            $allLogs = $query->get();
            $total = $allLogs->count();

            // Apply pagination
            $logsSlice = $allLogs->slice(($page - 1) * $perPage, $perPage)->values();

            $logs = $logsSlice->map(fn ($log) => $this->transformAttendanceLog($log));

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $logs,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'last_page' => (int)ceil($total / $perPage),
                ],
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Error fetching logs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], HttpCode::SERVER_ERROR);
        }
    }

    public function completeMissingLog(Request $request, $userId)
    {
        $request->validate([
            'work_date' => 'required|date',
            'missing_type' => 'required|in:check_in,check_out',
            'time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        try {
            $employee = Employee::where('user_id', $userId)->first();

            if (!$employee) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Employee not found',
                ], HttpCode::NOT_FOUND);
            }

            $workDate = Carbon::parse($request->work_date)->format('Y-m-d');
            $timeValue = strlen($request->time) === 5 ? $request->time . ':00' : $request->time;
            $manualTimestamp = Carbon::createFromFormat('Y-m-d H:i:s', $workDate . ' ' . $timeValue);

            $existingLogs = TimeLog::where('employee_id', $employee->id)
                ->whereDate('check_time', $workDate)
                ->orderBy('check_time')
                ->get();

            if ($existingLogs->isEmpty()) {
                return response()->json([
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'message' => 'No attendance scan found for this date',
                ], HttpCode::VALIDATION_ERROR);
            }

            if ($existingLogs->count() > 1) {
                return response()->json([
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'message' => 'This attendance record already has both check-in and check-out',
                ], HttpCode::VALIDATION_ERROR);
            }

            $existingScan = Carbon::parse($existingLogs->first()->check_time);

            if ($request->missing_type === 'check_in' && $manualTimestamp->gte($existingScan)) {
                return response()->json([
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'message' => 'Manual check-in must be earlier than the existing scan',
                ], HttpCode::VALIDATION_ERROR);
            }

            if ($request->missing_type === 'check_out' && $manualTimestamp->lte($existingScan)) {
                return response()->json([
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'message' => 'Manual check-out must be later than the existing scan',
                ], HttpCode::VALIDATION_ERROR);
            }

            $duplicate = TimeLog::where('employee_id', $employee->id)
                ->where('check_time', $manualTimestamp->format('Y-m-d H:i:s'))
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'message' => 'This timestamp already exists',
                ], HttpCode::VALIDATION_ERROR);
            }

            TimeLog::create([
                'employee_id' => $employee->id,
                'verify_code' => 'manual_' . $request->missing_type,
                'check_time' => $manualTimestamp,
            ]);

            $updatedLog = TimeLog::query()
                ->where('employee_id', $employee->id)
                ->whereDate('check_time', $workDate)
                ->select([
                    DB::raw('DATE(check_time) as work_date'),
                    DB::raw('MIN(check_time) as check_in'),
                    DB::raw('MAX(check_time) as check_out'),
                    DB::raw('COUNT(id) as scan_count'),
                ])
                ->groupBy('work_date')
                ->first();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Attendance updated successfully',
                'data' => $updatedLog ? $this->transformAttendanceLog($updatedLog) : null,
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update attendance',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], HttpCode::SERVER_ERROR);
        }
    }
}
