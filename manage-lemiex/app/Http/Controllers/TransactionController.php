<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\TransactionConstants;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Get transactions list with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Get params
        $params = [
            'per_page' => $request->input('per_page'),
            'page' => $request->input('page'),
            'seller_id' => $request->input('seller_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ];

        $result = $this->transactionService->getTransactionsList($params, $user);

        if (!$result['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => TransactionConstants::TRANSACTIONS_RETRIEVAL_FAILED,
                'error' => config('app.debug') ? $result['error'] : null
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => TransactionConstants::TRANSACTIONS_RETRIEVED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Add fund to wallet
     */
    public function addFund(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        if (($user->role->name ?? null) !== 'Seller') {
            return response()->json([
                'code' => HttpCode::FORBIDDEN,
                'status' => false,
                'message' => 'Only Seller can use this endpoint'
            ], HttpCode::FORBIDDEN);
        }

        // Validate request
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:Payment,Refund,Deposit,payment,refund,deposit',
                'amount' => 'required|numeric|min:0.01',
                'note' => 'nullable|string|max:500',
                'transaction_id' => 'required|string|max:255|unique:transactions,transaction_id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        }

        $result = $this->transactionService->addFund($validated, $user);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            $message = $result['message'] ?? TransactionConstants::TRANSACTION_CREATION_FAILED;

            return response()->json([
                'code' => $code,
                'status' => false,
                'message' => $message,
                'error' => config('app.debug') ? ($result['error'] ?? null) : null
            ], $code);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => TransactionConstants::TRANSACTION_CREATED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Export transactions to CSV
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Get params (same as index)
        $params = [
            'seller_id' => $request->input('seller_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
        ];

        $result = $this->transactionService->exportTransactions($params, $user);

        if (!$result['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => TransactionConstants::TRANSACTION_EXPORT_FAILED,
                'error' => config('app.debug') ? $result['error'] : null
            ], HttpCode::SERVER_ERROR);
        }

        // Generate filename
        $filename = TransactionConstants::EXPORT_FILENAME_PREFIX . date(TransactionConstants::EXPORT_DATE_FORMAT) . '.csv';

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => 'Export completed successfully',
            'data' => [
                'csv' => $result['data'],
                'filename' => $filename
            ]
        ], HttpCode::SUCCESS);
    }

    /**
     * Get sellers list for filter dropdown
     */
    public function getSellers(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        try {
            // Get only Seller role users
            $sellers = \App\Models\User::query()
                ->with('role:id,name')
                ->whereHas('role', function ($query) {
                    $query->where('name', 'Seller');
                })
                ->select(['id', 'username', 'email', 'role_id'])
                ->orderBy('username', 'asc')
                ->get();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'success' => true,
                'message' => 'Sellers retrieved successfully',
                'data' => $sellers
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to get sellers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve sellers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Admin: Add fund to a specific user's wallet
     */
    public function addFundToUser(Request $request, int $userId): JsonResponse
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Check if user is Admin/HR
        $admin->load('role');
        if (!in_array($admin->role->name, ['Admin', 'HR'], true)) {
            return response()->json([
                'code' => HttpCode::FORBIDDEN,
                'status' => false,
                'message' => 'Only Admin or HR can add funds to users'
            ], HttpCode::FORBIDDEN);
        }

        // Validate request
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:Deposit,Withdraw,deposit,withdraw',
                'amount' => 'required|numeric|min:0.01',
                'note' => 'nullable|string|max:500',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        }

        // Find target user
        $targetUser = \App\Models\User::with('profile')->find($userId);
        if (!$targetUser) {
            return response()->json([
                'code' => HttpCode::NOT_FOUND,
                'status' => false,
                'message' => 'User not found'
            ], HttpCode::NOT_FOUND);
        }

        if (!$targetUser->profile) {
            return response()->json([
                'code' => HttpCode::NOT_FOUND,
                'status' => false,
                'message' => 'User profile not found'
            ], HttpCode::NOT_FOUND);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $amount = (float) $validated['amount'];
            $type = ucfirst(strtolower($validated['type'])); // Normalize type
            $note = $validated['note'] ?? "Admin {$type} by {$admin->username}";

            // Determine if we should add or subtract
            $isDeduction = strtolower($type) === 'withdraw';

            // Calculate new balance
            $currentBalance = (float) ($targetUser->profile->wallet_balance ?? 0);

            if ($isDeduction) {
                $newBalance = $currentBalance - $amount;
                $transactionAmount = -$amount; // Save as negative in transaction
            } else {
                $newBalance = $currentBalance + $amount;
                $transactionAmount = $amount;
            }

            // Update wallet balance
            $targetUser->profile->wallet_balance = $newBalance;
            $targetUser->profile->save();

            // Generate transaction ID
            $transactionId = 'ADMIN-' . strtoupper(uniqid()) . '-' . time();

            // Normalize type to match DB enum
            $dbType = strtolower($type);

            // Map types to valid DB types: Withdraw -> payment, Deposit -> deposit
            if ($dbType === 'withdraw') {
                $dbType = 'payment';
            } else {
                $dbType = 'deposit'; // Default for Deposit
            }

            // Ensure type is valid enum (fallback to payment/deposit based on sign if needed)
            // Valid types: deposit, payment, refund, surcharge, refundnotwallet
            if (!in_array($dbType, \App\Enums\TransactionType::all())) {
                $dbType = $transactionAmount < 0 ? 'payment' : 'deposit';
            }

            // Create transaction record
            $transaction = \App\Models\Transaction::create([
                'seller_id' => $targetUser->id,
                'order_id' => null,
                'transaction_id' => $transactionId,
                'type' => $dbType,
                'amount' => $transactionAmount,
                'fee' => 0,
                'remaining_balance' => $newBalance,
                'status' => 'approved',
                'note' => $note,
                'approved_by' => $admin->id,
            ]);

            \Illuminate\Support\Facades\DB::commit();

            Log::info('Admin modified user fund', [
                'admin_id' => $admin->id,
                'target_user_id' => $targetUser->id,
                'type' => $type,
                'amount' => $amount,
                'old_balance' => $currentBalance,
                'new_balance' => $newBalance,
            ]);

            $actionText = $isDeduction ? "deducted \${$amount} from" : "added \${$amount} to";

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => "Successfully {$actionText} {$targetUser->username}'s wallet",
                'data' => [
                    'transaction_id' => $transactionId,
                    'user_id' => $targetUser->id,
                    'username' => $targetUser->username,
                    'old_balance' => $currentBalance,
                    'new_balance' => $newBalance,
                    'amount' => $amount,
                    'type' => $type,
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            Log::error('Failed to add fund to user', [
                'admin_id' => $admin->id,
                'target_user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to add fund: ' . $e->getMessage()
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get pending fund requests (Admin only)
     */
    public function getPendingFundRequests(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Check if user is Admin/Finance/HR
        $user->load('role');
        if (!in_array($user->role->name, ['Admin', 'Finance', 'HR'], true)) {
            return response()->json([
                'code' => HttpCode::FORBIDDEN,
                'status' => false,
                'message' => 'Only Admin, Finance or HR can view pending fund requests'
            ], HttpCode::FORBIDDEN);
        }

        $params = [
            'per_page' => $request->input('per_page', 20),
            'page' => $request->input('page', 1),
        ];

        $result = $this->transactionService->getPendingFundRequests($params);

        if (!$result['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to get pending fund requests',
                'error' => config('app.debug') ? $result['error'] : null
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => 'Pending fund requests retrieved successfully',
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Approve a pending fund request (Admin only)
     */
    public function approveFundRequest(Request $request, int $transactionId): JsonResponse
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Check if user is Admin/Finance/HR
        $admin->load('role');
        if (!in_array($admin->role->name, ['Admin', 'Finance', 'HR'], true)) {
            return response()->json([
                'code' => HttpCode::FORBIDDEN,
                'status' => false,
                'message' => 'Only Admin, Finance or HR can approve fund requests'
            ], HttpCode::FORBIDDEN);
        }

        $result = $this->transactionService->approveFundRequest($transactionId, $admin);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            return response()->json([
                'code' => $code,
                'status' => false,
                'message' => $result['message'] ?? 'Failed to approve fund request',
                'error' => config('app.debug') ? ($result['error'] ?? null) : null
            ], $code);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => $result['message'] ?? 'Fund request approved successfully',
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Reject a pending fund request (Admin only)
     */
    public function rejectFundRequest(Request $request, int $transactionId): JsonResponse
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => TransactionConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Check if user is Admin/Finance/HR
        $admin->load('role');
        if (!in_array($admin->role->name, ['Admin', 'Finance', 'HR'], true)) {
            return response()->json([
                'code' => HttpCode::FORBIDDEN,
                'status' => false,
                'message' => 'Only Admin, Finance or HR can reject fund requests'
            ], HttpCode::FORBIDDEN);
        }

        $reason = $request->input('reason');

        $result = $this->transactionService->rejectFundRequest($transactionId, $admin, $reason);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            return response()->json([
                'code' => $code,
                'status' => false,
                'message' => $result['message'] ?? 'Failed to reject fund request',
                'error' => config('app.debug') ? ($result['error'] ?? null) : null
            ], $code);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => $result['message'] ?? 'Fund request rejected',
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }
}
