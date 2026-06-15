<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Enums\UserRole;
use App\Models\PartnerStore;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerStoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $currentUser->load('role');

        $query = PartnerStore::query()
            ->with([
                'partnerApp:id,name,slug,proxy_status,status',
                'user:id,username,email',
            ])
            ->orderByDesc('id');

        if (($currentUser->role->name ?? null) === UserRole::all()[UserRole::SELLER]) {
            $query->where('user_id', $currentUser->id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('account_no', 'like', "%{$search}%")
                    ->orWhereHas('partnerApp', function ($relation) use ($search) {
                        $relation->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($relation) use ($search) {
                        $relation->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($partnerAppId = $request->input('partner_app_id')) {
            $query->where('partner_app_id', $partnerAppId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) ($request->input('per_page') ?: 10);
        $page = (int) ($request->input('page') ?: 1);
        $stores = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Partner stores retrieved successfully',
            'data' => [
                'partner_stores' => $stores->items(),
                'pagination' => [
                    'current_page' => $stores->currentPage(),
                    'per_page' => $stores->perPage(),
                    'total' => $stores->total(),
                    'last_page' => $stores->lastPage(),
                ],
            ],
        ], HttpCode::SUCCESS);
    }

    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $currentUser->load('role');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:partner_stores,code',
            'user_id' => 'required|exists:users,id',
            'partner_app_id' => 'required|exists:partner_apps,id',
        ]);

        if (($currentUser->role->name ?? null) === UserRole::all()[UserRole::SELLER]
            && (int) $validated['user_id'] !== (int) $currentUser->id) {
            return response()->json([
                'code' => HttpCode::FORBIDDEN,
                'status' => false,
                'message' => 'You can only create partner stores for yourself',
            ], HttpCode::FORBIDDEN);
        }

        $store = PartnerStore::create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'user_id' => $validated['user_id'],
            'partner_app_id' => $validated['partner_app_id'],
            'status' => 'Active',
            'account_no' => null,
            'total_order' => 0,
        ]);

        $store->load(['partnerApp:id,name,slug,proxy_status,status', 'user:id,username,email']);

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Partner store created successfully',
            'data' => $store,
        ], HttpCode::SUCCESS);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $store = PartnerStore::with(['partnerApp:id,name,slug,proxy_status,status', 'user:id,username,email'])->find($id);

        if (!$store) {
            return response()->json([
                'code' => HttpCode::NOT_FOUND,
                'status' => false,
                'message' => 'Partner store not found',
            ], HttpCode::NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:partner_stores,code,' . $id,
            'user_id' => 'required|exists:users,id',
            'partner_app_id' => 'required|exists:partner_apps,id',
            'status' => 'nullable|string|max:50',
            'account_no' => 'nullable|string|max:255',
        ]);

        $store->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'user_id' => $validated['user_id'],
            'partner_app_id' => $validated['partner_app_id'],
            'status' => $validated['status'] ?? $store->status,
            'account_no' => $validated['account_no'] ?? null,
        ]);

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Partner store updated successfully',
            'data' => $store->fresh(['partnerApp:id,name,slug,proxy_status,status', 'user:id,username,email']),
        ], HttpCode::SUCCESS);
    }

    public function users(): JsonResponse
    {
        $users = User::query()
            ->with('role:id,name')
            ->whereHas('role', function ($query) {
                $query->whereIn('name', [
                    UserRole::all()[UserRole::SELLER],
                    UserRole::all()[UserRole::STAFF],
                    UserRole::all()[UserRole::QC],
                    UserRole::all()[UserRole::PACKING],
                    UserRole::all()[UserRole::SHIPOUT],
                ]);
            })
            ->select(['id', 'username', 'email', 'role_id'])
            ->orderBy('username')
            ->get();

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Partner store users retrieved successfully',
            'data' => $users,
        ], HttpCode::SUCCESS);
    }
}
