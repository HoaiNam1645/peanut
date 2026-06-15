<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\StoreConstants;
use App\Enums\UserStatus;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{
    protected $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }
    /**
     * Get all stores (for admin) or user's stores (for sellers)
     * Simple endpoint for dropdowns and order creation
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'code' => HttpCode::UNAUTHORIZED,
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Load role relationship
            $user->load('role');

            // Admin/HR can see all stores, Sellers only see their own
            if ($user->role && in_array($user->role->name, ['Admin', 'HR'], true)) {
                $stores = Store::with('user')->get();
            } else {
                $stores = Store::where('user_id', $user->id)->get();
            }

            // Make api_key visible for this response (needed for order creation)
            $stores->makeVisible('api_key');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Stores retrieved successfully',
                'data' => $stores
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve stores',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get stores list with pagination and filters
     * Optimized endpoint for stores management page
     */
    public function getStoresList(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => StoreConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Get params
        $params = [
            'per_page' => $request->input('per_page'),
            'page' => $request->input('page'),
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ];

        $result = $this->storeService->getStoresList($params, $user);

        if (!$result['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => StoreConstants::STORES_RETRIEVAL_FAILED,
                'error' => config('app.debug') ? $result['error'] : null
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => StoreConstants::STORES_RETRIEVED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Get store by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $store = Store::with('user')->find($id);

            if (!$store) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => StoreConstants::STORE_NOT_FOUND
                ], HttpCode::NOT_FOUND);
            }

            // Make api_key visible for this response
            $store->makeVisible('api_key');

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => StoreConstants::STORE_RETRIEVED,
                'data' => $store
            ], HttpCode::SUCCESS);

        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => StoreConstants::STORES_RETRIEVAL_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Create new store
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => StoreConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Validate request
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'name' => 'required|string|max:255|unique:stores,name',
                'api_key' => 'required|string|size:' . StoreConstants::API_KEY_LENGTH . '|unique:stores,api_key|regex:' . StoreConstants::API_KEY_REGEX,
            ], [
                'name.unique' => StoreConstants::NAME_UNIQUE_ERROR,
                'api_key.unique' => StoreConstants::API_KEY_UNIQUE_ERROR,
                'api_key.regex' => StoreConstants::API_KEY_FORMAT_ERROR,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        }

        $result = $this->storeService->createStore($validated, $user);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            $message = $result['message'] ?? StoreConstants::STORE_CREATION_FAILED;
            
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
            'message' => StoreConstants::STORE_CREATED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Get users list for store creation (Sellers only)
     */
    public function getUsers(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => StoreConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        $result = $this->storeService->getUsersForStoreCreation();

        if (!$result['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => StoreConstants::USERS_RETRIEVAL_FAILED,
                'error' => config('app.debug') ? $result['error'] : null
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => StoreConstants::USERS_RETRIEVED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Update store
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => StoreConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Validate request
        try {
            $validated = $request->validate([
                'user_id' => 'sometimes|exists:users,id',
                'name' => 'sometimes|string|max:255|unique:stores,name,' . $id,
                'api_key' => 'sometimes|string|size:' . StoreConstants::API_KEY_LENGTH . '|unique:stores,api_key,' . $id . '|regex:' . StoreConstants::API_KEY_REGEX,
                'status' => 'sometimes|in:' . implode(',', UserStatus::all()),
            ], [
                'name.unique' => StoreConstants::NAME_UNIQUE_ERROR,
                'api_key.unique' => StoreConstants::API_KEY_UNIQUE_ERROR,
                'api_key.regex' => StoreConstants::API_KEY_FORMAT_ERROR,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        }

        $result = $this->storeService->updateStore($id, $validated, $user);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            $message = $result['message'] ?? StoreConstants::STORE_UPDATE_FAILED;
            
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
            'message' => StoreConstants::STORE_UPDATED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }
}
