<?php

namespace App\Services;

use App\Constants\HttpCode;
use App\Constants\TransactionConstants;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TransactionService
{
    /**
     * Get transactions list with pagination and filters
     */
    public function getTransactionsList(array $params, User $currentUser): array
    {
        try {
            $userRole = $currentUser->role->name ?? null;

            // Pagination
            $perPage = $params['per_page'] ?? TransactionConstants::DEFAULT_PER_PAGE;
            $page = $params['page'] ?? TransactionConstants::DEFAULT_PAGE;

            // Build query with eager loading
            $query = Transaction::query()
                ->with(['seller:id,username,email', 'order:id,order_stt,ref_id,store_id', 'order.store:id,name'])
                ->select([
                    'id',
                    'seller_id',
                    'order_id',
                    'transaction_id',
                    'type',
                    'type_surcharge',
                    'amount',
                    'fee',
                    'remaining_balance',
                    'status',
                    'note',
                    'created_at'
                ]);

            // Role-based filtering
            if ($userRole === UserRole::all()[UserRole::SELLER]) {
                $query->where('seller_id', $currentUser->id);
            }
            // Admin/Finance sees all transactions

            // Seller filter (Admin/Finance only)
            if (
                in_array($userRole, [UserRole::all()[UserRole::ADMIN], UserRole::all()[UserRole::FINANCE]])
                && !empty($params['seller_id'])
            ) {
                $query->where('seller_id', $params['seller_id']);
            }

            // Date range filter
            if (!empty($params['date_from'])) {
                $query->whereDate('created_at', '>=', $params['date_from']);
            }
            if (!empty($params['date_to'])) {
                $query->whereDate('created_at', '<=', $params['date_to']);
            }

            // Type filter
            if (!empty($params['type'])) {
                $query->where('type', $params['type']);
            }

            // Status filter
            if (!empty($params['status'])) {
                $query->where('status', $params['status']);
            }

            // Search filter
            if (!empty($params['search'])) {
                $search = $params['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_id', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%");

                    // Search by order_id if search is numeric
                    if (is_numeric($search)) {
                        $q->orWhere('order_id', $search);
                    }

                    // Search in related order
                    $q->orWhereHas('order', function ($orderQuery) use ($search) {
                        $orderQuery->where('order_stt', 'like', "%{$search}%")
                            ->orWhere('ref_id', 'like', "%{$search}%");

                        // Also search by order id if numeric
                        if (is_numeric($search)) {
                            $orderQuery->orWhere('id', $search);
                        }
                    });

                    // Search by seller username
                    $q->orWhereHas('seller', function ($sellerQuery) use ($search) {
                        $sellerQuery->where('username', 'like', "%{$search}%");
                    });
                });
            }

            // Sorting
            $sortBy = $params['sort_by'] ?? TransactionConstants::DEFAULT_SORT_BY;
            $sortOrder = $params['sort_order'] ?? TransactionConstants::DEFAULT_SORT_ORDER;

            if (in_array($sortBy, TransactionConstants::SORTABLE_FIELDS)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy(TransactionConstants::DEFAULT_SORT_BY, TransactionConstants::DEFAULT_SORT_ORDER);
            }

            // Clone query for calculating totals BEFORE pagination
            $totalAmountQuery = clone $query;
            $totalAmount = $totalAmountQuery->sum('amount');

            // Paginate
            $transactions = $query->paginate($perPage, ['*'], 'page', $page);

            // Calculate page amount from current page transactions
            $pageAmount = $transactions->getCollection()->sum('amount');

            // Transform data
            $transformedTransactions = $transactions->getCollection()->map(function ($transaction) {
                return $this->transformTransactionData($transaction);
            });

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transformedTransactions,
                    'pagination' => [
                        'current_page' => $transactions->currentPage(),
                        'per_page' => $transactions->perPage(),
                        'total' => $transactions->total(),
                        'last_page' => $transactions->lastPage(),
                        'from' => $transactions->firstItem(),
                        'to' => $transactions->lastItem(),
                    ],
                    'summary' => [
                        'total_amount' => round($totalAmount, 2),
                        'page_amount' => round($pageAmount, 2),
                    ]
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to get transactions list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Add fund to wallet - Creates PENDING transaction that requires admin approval
     */
    public function addFund(array $data, User $currentUser): array
    {
        try {
            DB::beginTransaction();

            $type = $data['type'];
            $amount = $data['amount'];
            $note = $data['note'] ?? null;
            $transactionId = $data['transaction_id'];

            // Get current user's profile (seller adding fund to their own wallet)
            $sellerProfile = UserProfile::where('user_id', $currentUser->id)->first();

            if (!$sellerProfile) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => 'User profile not found'
                ];
            }

            // Get current balance (for reference only, NOT updating yet)
            $currentBalance = $sellerProfile->wallet_balance ?? 0;

            // Create transaction record with PENDING status
            // Wallet will be updated only when admin approves
            $transaction = Transaction::create([
                'seller_id' => $currentUser->id,
                'order_id' => null,
                'transaction_id' => $transactionId,
                'type' => $type,
                'amount' => $amount,
                'fee' => 0,
                'remaining_balance' => $currentBalance, // Current balance, not new balance
                'status' => TransactionStatus::PENDING, // PENDING instead of APPROVED
                'note' => $note,
                'approved_by' => null, // Will be set when admin approves
            ]);

            DB::commit();

            Log::info('Fund request created (pending approval)', [
                'transaction_id' => $transaction->id,
                'seller_id' => $currentUser->id,
                'type' => $type,
                'amount' => $amount,
                'status' => 'pending'
            ]);

            return [
                'success' => true,
                'data' => $transaction->load(['seller', 'order']),
                'message' => 'Fund request submitted successfully. Waiting for admin approval.'
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create fund request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Approve a pending fund request (Admin only)
     */
    public function approveFundRequest(int $transactionId, User $admin): array
    {
        try {
            DB::beginTransaction();

            $transaction = Transaction::find($transactionId);

            if (!$transaction) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => 'Transaction not found'
                ];
            }

            if ($transaction->status !== TransactionStatus::PENDING) {
                return [
                    'success' => false,
                    'code' => HttpCode::BAD_REQUEST,
                    'message' => 'Transaction is not pending'
                ];
            }

            // Get seller's profile
            $sellerProfile = UserProfile::where('user_id', $transaction->seller_id)->first();

            if (!$sellerProfile) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => 'Seller profile not found'
                ];
            }

            // Calculate new balance
            $currentBalance = $sellerProfile->wallet_balance ?? 0;
            $type = strtolower($transaction->type);

            if ($type === 'payment' || $type === 'withdraw') {
                $newBalance = $currentBalance - $transaction->amount;
            } else {
                $newBalance = $currentBalance + $transaction->amount;
            }

            // Update wallet balance
            $sellerProfile->wallet_balance = $newBalance;
            $sellerProfile->save();

            // Update transaction
            $transaction->status = TransactionStatus::APPROVED;
            $transaction->approved_by = $admin->id;
            $transaction->remaining_balance = $newBalance;
            $transaction->save();

            DB::commit();

            Log::info('Fund request approved', [
                'transaction_id' => $transaction->id,
                'approved_by' => $admin->id,
                'seller_id' => $transaction->seller_id,
                'amount' => $transaction->amount,
                'new_balance' => $newBalance
            ]);

            return [
                'success' => true,
                'data' => $transaction->load(['seller']),
                'message' => 'Fund request approved successfully'
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to approve fund request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reject a pending fund request (Admin only)
     */
    public function rejectFundRequest(int $transactionId, User $admin, ?string $reason = null): array
    {
        try {
            DB::beginTransaction();

            $transaction = Transaction::find($transactionId);

            if (!$transaction) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => 'Transaction not found'
                ];
            }

            if ($transaction->status !== TransactionStatus::PENDING) {
                return [
                    'success' => false,
                    'code' => HttpCode::BAD_REQUEST,
                    'message' => 'Transaction is not pending'
                ];
            }

            // Update transaction status to rejected
            $transaction->status = TransactionStatus::REJECTED;
            $transaction->approved_by = $admin->id;
            if ($reason) {
                $transaction->note = ($transaction->note ? $transaction->note . ' | ' : '') . 'Rejected: ' . $reason;
            }
            $transaction->save();

            DB::commit();

            Log::info('Fund request rejected', [
                'transaction_id' => $transaction->id,
                'rejected_by' => $admin->id,
                'seller_id' => $transaction->seller_id,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'data' => $transaction->load(['seller']),
                'message' => 'Fund request rejected'
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to reject fund request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get pending fund requests (Admin only)
     */
    public function getPendingFundRequests(array $params = []): array
    {
        try {
            $perPage = $params['per_page'] ?? 20;
            $page = $params['page'] ?? 1;

            $query = Transaction::query()
                ->with(['seller:id,username,email'])
                ->where('status', TransactionStatus::PENDING)
                ->whereIn('type', ['deposit', 'Deposit', 'refund', 'Refund'])
                ->orderBy('created_at', 'asc'); // Oldest first

            $transactions = $query->paginate($perPage, ['*'], 'page', $page);

            $transformedTransactions = $transactions->getCollection()->map(function ($transaction) {
                return $this->transformTransactionData($transaction);
            });

            return [
                'success' => true,
                'data' => [
                    'transactions' => $transformedTransactions,
                    'pagination' => [
                        'current_page' => $transactions->currentPage(),
                        'per_page' => $transactions->perPage(),
                        'total' => $transactions->total(),
                        'last_page' => $transactions->lastPage(),
                    ]
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to get pending fund requests', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Export transactions to CSV
     */
    public function exportTransactions(array $params, User $currentUser): array
    {
        try {
            $userRole = $currentUser->role->name ?? null;

            // Build query (same as getTransactionsList but without pagination)
            $query = Transaction::query()
                ->with(['seller:id,username,email', 'order:id,order_stt,ref_id'])
                ->select([
                    'id',
                    'seller_id',
                    'order_id',
                    'transaction_id',
                    'type',
                    'type_surcharge',
                    'amount',
                    'fee',
                    'remaining_balance',
                    'status',
                    'note',
                    'created_at'
                ]);

            // Apply same filters as getTransactionsList
            if ($userRole === UserRole::all()[UserRole::SELLER]) {
                $query->where('seller_id', $currentUser->id);
            }

            if (
                in_array($userRole, [UserRole::all()[UserRole::ADMIN], UserRole::all()[UserRole::FINANCE]])
                && !empty($params['seller_id'])
            ) {
                $query->where('seller_id', $params['seller_id']);
            }

            if (!empty($params['date_from'])) {
                $query->whereDate('created_at', '>=', $params['date_from']);
            }
            if (!empty($params['date_to'])) {
                $query->whereDate('created_at', '<=', $params['date_to']);
            }
            if (!empty($params['type'])) {
                $query->where('type', $params['type']);
            }
            if (!empty($params['status'])) {
                $query->where('status', $params['status']);
            }

            $query->orderBy('created_at', 'desc');

            $transactions = $query->get();

            // Generate CSV data
            $csvData = $this->generateCSV($transactions);

            return [
                'success' => true,
                'data' => $csvData
            ];
        } catch (Exception $e) {
            Log::error('Failed to export transactions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate transaction ID
     */
    protected function generateTransactionId(): string
    {
        return 'TXN-' . strtoupper(uniqid()) . '-' . time();
    }

    /**
     * Transform transaction data for response
     */
    protected function transformTransactionData($transaction): array
    {
        return [
            'id' => $transaction->id,
            'seller' => [
                'id' => $transaction->seller->id ?? null,
                'username' => $transaction->seller->username ?? 'N/A',
                'email' => $transaction->seller->email ?? 'N/A',
            ],
            'order' => $transaction->order ? [
                'id' => $transaction->order->id,
                'order_stt' => $transaction->order->order_stt,
                'ref_id' => $transaction->order->ref_id,
                'store' => $transaction->order->store ? [
                    'id' => $transaction->order->store->id,
                    'name' => $transaction->order->store->name,
                ] : null,
            ] : null,
            'transaction_id' => $transaction->transaction_id,
            'type' => $transaction->type,
            'type_surcharge' => $transaction->type_surcharge,
            'amount' => $transaction->amount,
            'fee' => $transaction->fee,
            'remaining_balance' => $transaction->remaining_balance,
            'status' => $transaction->status,
            'note' => $transaction->note,
            'created_at' => $transaction->created_at,
        ];
    }

    /**
     * Generate CSV from transactions
     */
    protected function generateCSV($transactions): string
    {
        $csv = "ID,Transaction ID,Seller,Order ID,Type,Amount,Fee,Remaining Balance,Status,Note,Created At\n";

        foreach ($transactions as $transaction) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%.2f,%.2f,%.2f,%s,%s,%s\n",
                $transaction->id,
                $transaction->transaction_id,
                $transaction->seller->username ?? 'N/A',
                $transaction->order ? $transaction->order->order_stt : 'N/A',
                $transaction->type,
                $transaction->amount,
                $transaction->fee,
                $transaction->remaining_balance,
                $transaction->status,
                str_replace(["\n", "\r", ","], [" ", " ", ";"], $transaction->note ?? ''),
                $transaction->created_at->format('Y-m-d H:i:s')
            );
        }

        return $csv;
    }
}
