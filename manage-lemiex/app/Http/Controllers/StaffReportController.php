<?php

namespace App\Http\Controllers;

use App\Models\OrderItemWorkflow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfDay()->toDateTimeString());
        $dateTo = $request->input('date_to', now()->endOfDay()->toDateTimeString());
        $staffId = $request->input('staff_id');

        // Base query
        $query = OrderItemWorkflow::query()
            ->whereNotNull('completed_by')
            ->whereBetween('completed_at', [$dateFrom, $dateTo]);

        if ($staffId) {
            $query->where('completed_by', $staffId);
        }

        $totalItemsInPeriod = OrderItemWorkflow::query()
            ->whereNotNull('completed_by')
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->count();

        // 1. Staff Performance Summary
        // Optimize: grouped query
        $summary = OrderItemWorkflow::query()
            ->select('completed_by', DB::raw('count(*) as items_processed'))
            ->whereNotNull('completed_by')
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->when($staffId, function ($q) use ($staffId) {
                return $q->where('completed_by', $staffId);
            })
            ->groupBy('completed_by')
            ->with('completedByUser:id,username') // Eager load user info
            ->get()
            ->map(function ($item) use ($totalItemsInPeriod) {
                return [
                    'staff_id' => $item->completed_by,
                    'staff_name' => $item->completedByUser->username ?? 'Unknown',
                    'username' => $item->completedByUser->username ?? 'Unknown',
                    'items_processed' => $item->items_processed,
                    'percentage' => $totalItemsInPeriod > 0 ? round(($item->items_processed / $totalItemsInPeriod) * 100, 2) : 0,
                ];
            });

        // 2. Processing Activity Details
        // Optimize: explicit column selection
        $details = OrderItemWorkflow::query()
            ->select(['id', 'order_item_id', 'completed_by', 'completed_at', 'stage', 'position']) // Select only needed columns
            ->whereNotNull('completed_by')
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->when($staffId, function ($q) use ($staffId) {
                return $q->where('completed_by', $staffId);
            })
            ->with([
                'completedByUser:id,username',
                'orderItem:id,order_id', // Load minimal OrderItem data
                'orderItem.order:id,order_stt,ref_id' // Load minimal Order data
            ])
            ->orderBy('completed_at', 'desc')
            ->paginate($request->input('per_page', 20));

        // Format details
        $formattedDetails = $details->getCollection()->map(function ($item) {
            return [
                'id' => $item->id,
                'staff_name' => $item->completedByUser->username ?? 'Unknown',
                'username' => $item->completedByUser->username ?? 'Unknown',
                'order_id' => $item->orderItem->order->id ?? 'N/A',
                'item_id' => $item->orderItem->id ?? 'N/A',
                'meta_key' => $item->stage . ($item->position ? " - {$item->position}" : ''),
                'processed_at' => $item->completed_at->toDateTimeString(),
            ];
        });

        $details->setCollection($formattedDetails);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'details' => $details,
                'total_processed_in_period' => $totalItemsInPeriod
            ]
        ]);
    }

    /**
     * Get list of staff members for filter
     */
    public function getStaffList()
    {
        $users = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['Staff', 'Admin', 'HR', 'QC', 'Packing', 'Shipout']);
        })->get(['id', 'username']);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }
}
