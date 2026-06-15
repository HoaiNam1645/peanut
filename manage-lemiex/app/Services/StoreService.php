<?php

namespace App\Services;

use App\Constants\HttpCode;
use App\Constants\StoreConstants;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class StoreService
{
    /**
     * Get stores list with pagination and filters
     */
    public function getStoresList(array $params, User $currentUser): array
    {
        try {
            $userRole = $currentUser->role->name ?? null;
            
            // Pagination
            $perPage = $params['per_page'] ?? StoreConstants::DEFAULT_PER_PAGE;
            $page = $params['page'] ?? StoreConstants::DEFAULT_PAGE;

            // Build query with eager loading
            $query = Store::query()
                ->with(['user:id,username,email,status', 'user.role:id,name'])
                ->select(['id', 'user_id', 'name', 'created_at', 'updated_at']);

            // Role-based filtering
            if ($userRole === UserRole::all()[UserRole::SELLER]) {
                $query->where('user_id', $currentUser->id);
            }
            // Admin sees all stores (no additional filter)

            // Search filter
            if (!empty($params['search'])) {
                $search = $params['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($userQuery) use ($search) {
                          $userQuery->where('username', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            // Status filter (based on user status)
            if (!empty($params['status'])) {
                $status = $params['status'];
                $query->whereHas('user', function ($userQuery) use ($status) {
                    $userQuery->where('status', $status);
                });
            }

            // Sorting
            $sortBy = $params['sort_by'] ?? StoreConstants::DEFAULT_SORT_BY;
            $sortOrder = $params['sort_order'] ?? StoreConstants::DEFAULT_SORT_ORDER;
            
            if (in_array($sortBy, StoreConstants::SORTABLE_FIELDS)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy(StoreConstants::DEFAULT_SORT_BY, StoreConstants::DEFAULT_SORT_ORDER);
            }

            // Paginate
            $stores = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data
            $transformedStores = $stores->getCollection()->map(function ($store) {
                return $this->transformStoreData($store);
            });

            return [
                'success' => true,
                'data' => [
                    'stores' => $transformedStores,
                    'pagination' => [
                        'current_page' => $stores->currentPage(),
                        'per_page' => $stores->perPage(),
                        'total' => $stores->total(),
                        'last_page' => $stores->lastPage(),
                        'from' => $stores->firstItem(),
                        'to' => $stores->lastItem(),
                    ]
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to get stores list', [
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
     * Get users for store creation (Sellers only)
     */
    public function getUsersForStoreCreation(): array
    {
        try {
            $users = User::query()
                ->with('role:id,name')
                ->whereHas('role', function ($query) {
                    $query->where('name', UserRole::all()[UserRole::SELLER]);
                })
                ->where('status', UserStatus::ACTIVE)
                ->select(['id', 'username', 'email', 'role_id', 'status'])
                ->orderBy('username', 'asc')
                ->get();

            return [
                'success' => true,
                'data' => $users
            ];
        } catch (Exception $e) {
            Log::error('Failed to get users', [
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
     * Create new store
     */
    public function createStore(array $data, User $currentUser): array
    {
        try {
            $userRole = $currentUser->role->name ?? null;

            // Seller can only create store for themselves
            if ($userRole === UserRole::all()[UserRole::SELLER] && $data['user_id'] != $currentUser->id) {
                return [
                    'success' => false,
                    'code' => HttpCode::FORBIDDEN,
                    'message' => StoreConstants::FORBIDDEN_CREATE_FOR_OTHER
                ];
            }

            // Create store
            $store = Store::create([
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'api_key' => $data['api_key'],
            ]);

            // Load relationships
            $store->load('user');

            Log::info('Store created successfully', [
                'store_id' => $store->id,
                'user_id' => $data['user_id'],
                'created_by' => $currentUser->id
            ]);

            return [
                'success' => true,
                'data' => $store
            ];
        } catch (Exception $e) {
            Log::error('Failed to create store', [
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
     * Update store
     */
    public function updateStore(int $storeId, array $data, User $currentUser): array
    {
        try {
            $userRole = $currentUser->role->name ?? null;

            // Find store
            $store = Store::with('user')->find($storeId);

            if (!$store) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => StoreConstants::STORE_NOT_FOUND
                ];
            }

            // Seller can only edit their own stores
            if ($userRole === UserRole::all()[UserRole::SELLER] && $store->user_id != $currentUser->id) {
                return [
                    'success' => false,
                    'code' => HttpCode::FORBIDDEN,
                    'message' => StoreConstants::FORBIDDEN_EDIT_OTHER_STORE
                ];
            }

            // Seller cannot change user_id
            if ($userRole === UserRole::all()[UserRole::SELLER] && 
                isset($data['user_id']) && 
                $data['user_id'] != $store->user_id) {
                return [
                    'success' => false,
                    'code' => HttpCode::FORBIDDEN,
                    'message' => StoreConstants::FORBIDDEN_CHANGE_OWNER
                ];
            }

            // Update store fields
            if (isset($data['user_id'])) {
                $store->user_id = $data['user_id'];
            }
            if (isset($data['name'])) {
                $store->name = $data['name'];
            }
            if (isset($data['api_key'])) {
                $store->api_key = $data['api_key'];
            }

            $store->save();

            // Update user status if provided (Admin only)
            if (isset($data['status']) && $userRole === UserRole::all()[UserRole::ADMIN]) {
                $storeUser = User::find($store->user_id);
                if ($storeUser) {
                    $storeUser->status = $data['status'];
                    $storeUser->save();
                }
            }

            // Reload relationships
            $store->load('user');

            Log::info('Store updated successfully', [
                'store_id' => $store->id,
                'updated_by' => $currentUser->id
            ]);

            return [
                'success' => true,
                'data' => $store
            ];
        } catch (Exception $e) {
            Log::error('Failed to update store', [
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
     * Transform store data for response
     */
    protected function transformStoreData($store): array
    {
        return [
            'id' => $store->id,
            'name' => $store->name,
            'user' => [
                'id' => $store->user->id ?? null,
                'username' => $store->user->username ?? 'N/A',
                'email' => $store->user->email ?? 'N/A',
                'status' => $store->user->status ?? 'Unknown',
                'role' => $store->user->role->name ?? 'N/A',
            ],
            'status' => $store->user->status ?? 'Unknown',
            'created_at' => $store->created_at,
            'updated_at' => $store->updated_at,
        ];
    }
}

