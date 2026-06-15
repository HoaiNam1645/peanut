<?php

namespace App\Services;

use App\Constants\HttpCode;
use App\Constants\MsgCode;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserServices
{
    protected $userModel;

    public function __construct(User $user)
    {
        $this->userModel = $user;
    }

    public function getUsers($request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $roleId = $request->input('role_id', '');
            $tier = $request->input('tier', '');

            $query = User::with(['role', 'profile']);

            // Search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhereHas('profile', function ($profileQuery) use ($search) {
                            $profileQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            }

            // Status filter
            if ($status !== '' && $status !== null) {
                $query->where('status', $status);
            }

            // Role filter
            if ($roleId && $roleId !== '') {
                $query->where('role_id', $roleId);
            }

            // Tier filter - skip if empty
            if ($tier !== '' && $tier !== null) {
                $query->whereHas('profile', function ($q) use ($tier) {
                    $q->where('private_seller', $tier);
                });
            }

            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'msgCode' => MsgCode::SUCCESS,
                'message' => 'Lấy danh sách người dùng thành công',
                'data' => $users
            ];
        } catch (\Exception $e) {
            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'msgCode' => MsgCode::SERVER_ERROR,
                'message' => 'Đã xảy ra lỗi khi lấy danh sách người dùng',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    public function getUserById($id)
    {
        try {
            $user = User::with(['role', 'profile', 'stores'])->find($id);

            if (!$user) {
                return [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'msgCode' => MsgCode::NOT_FOUND,
                    'message' => 'Không tìm thấy người dùng'
                ];
            }

            // Include api_key in response only for admin
            $userData = $user->toArray();
            $currentUser = \Illuminate\Support\Facades\Auth::user();
            if ($currentUser && $currentUser->role_id == 1) {
                $userData['api_key'] = $user->api_key ?: 'N/A';
            }

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'msgCode' => MsgCode::SUCCESS,
                'message' => 'Lấy thông tin người dùng thành công',
                'data' => $userData
            ];
        } catch (\Exception $e) {
            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'msgCode' => MsgCode::SERVER_ERROR,
                'message' => 'Đã xảy ra lỗi khi lấy thông tin người dùng',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    public function updateUser($request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'msgCode' => MsgCode::NOT_FOUND,
                    'message' => 'Không tìm thấy người dùng'
                ];
            }

            // Build validation rules - password is optional
            $rules = [
                'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
                'username' => 'sometimes|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/|unique:users,username,' . $id,
                'role_id' => 'sometimes|integer|exists:roles,id',
                'status' => 'sometimes|string|in:Unconfirmed,Active,Banned',
                'first_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:255',
                'birthday' => 'nullable|date',
                'webhook_url' => 'nullable|url|max:255',
                'telegram_id' => 'nullable|string|max:100',
                'api_key' => 'nullable|string|max:255',
                'max_debit' => 'nullable|numeric|min:0',
                'max_date_debit' => 'nullable|integer|min:0',
                'min_date_debit' => 'nullable|integer|min:0',
                'is_support_us' => 'nullable|boolean',
                'tier_id' => 'nullable|integer|in:0,1,2,3',
                'private_seller' => 'nullable|integer|in:0,1,2,3', // Alias for tier_id
            ];

            // Add password validation only if password is provided
            if ($request->filled('password')) {
                $rules['password'] = 'string|min:6|max:255';
                $rules['password_confirmation'] = 'required_with:password|same:password';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return [
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'msgCode' => MsgCode::VALIDATION_ERROR,
                    'message' => $validator->errors(),
                ];
            }

            // Update user table
            if ($request->has('email')) $user->email = $request->email;
            if ($request->has('username')) $user->username = $request->username;
            if ($request->has('role_id')) $user->role_id = $request->role_id;
            if ($request->has('status')) $user->status = $request->status;
            if ($request->has('api_key')) $user->api_key = $request->api_key;
            $user->save();

            // Update password if provided
            if ($request->filled('password')) {
                $authProvider = \App\Models\AuthProvider::where('user_id', $user->id)
                    ->where('provider', 'local')
                    ->first();

                if ($authProvider) {
                    $authProvider->password = \Illuminate\Support\Facades\Hash::make($request->password);
                    $authProvider->save();
                } else {
                    // Create auth provider if not exists
                    \App\Models\AuthProvider::create([
                        'user_id' => $user->id,
                        'provider' => 'local',
                        'password' => \Illuminate\Support\Facades\Hash::make($request->password),
                    ]);
                }
            }

            // Update profile
            if ($user->profile) {
                if ($request->has('first_name')) $user->profile->first_name = $request->first_name;
                if ($request->has('last_name')) $user->profile->last_name = $request->last_name;
                if ($request->has('phone')) $user->profile->phone = $request->phone;
                if ($request->has('address')) $user->profile->address = $request->address;
                if ($request->has('birthday')) $user->profile->birthday = $request->birthday;
                if ($request->has('webhook_url')) $user->profile->webhook_url = $request->webhook_url;
                if ($request->has('telegram_id')) $user->profile->telegram_id = $request->telegram_id;
                if ($request->has('is_support_us')) $user->profile->is_support_us = $request->is_support_us;
                if ($request->has('tier_id')) $user->profile->private_seller = $request->tier_id;
                elseif ($request->has('private_seller')) $user->profile->private_seller = $request->private_seller;
                if ($request->has('max_debit')) $user->profile->max_debit = $request->max_debit;
                if ($request->has('max_date_debit')) $user->profile->max_date_debit = $request->max_date_debit;
                if ($request->has('min_date_debit')) $user->profile->min_date_debit = $request->min_date_debit;
                $user->profile->save();
            }

            $user->load(['role', 'profile']);

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'msgCode' => MsgCode::SUCCESS,
                'message' => 'Cập nhật người dùng thành công',
                'data' => $user
            ];
        } catch (\Exception $e) {
            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'msgCode' => MsgCode::SERVER_ERROR,
                'message' => 'Đã xảy ra lỗi khi cập nhật người dùng',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'msgCode' => MsgCode::NOT_FOUND,
                    'message' => 'Không tìm thấy người dùng'
                ];
            }

            // Cannot delete admin account
            if ($user->role_id == UserRole::ADMIN) {
                return [
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'msgCode' => MsgCode::FORBIDDEN,
                    'message' => 'Không được phép xóa tài khoản Admin'
                ];
            }

            // Cannot delete yourself
            $currentUserId = \Illuminate\Support\Facades\Auth::id();
            if ($currentUserId && $user->id == $currentUserId) {
                return [
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'msgCode' => MsgCode::FORBIDDEN,
                    'message' => 'Không thể xóa chính tài khoản của bạn'
                ];
            }

            $user->delete();

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'msgCode' => MsgCode::SUCCESS,
                'message' => 'Xóa người dùng thành công'
            ];
        } catch (\Exception $e) {
            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'msgCode' => MsgCode::SERVER_ERROR,
                'message' => 'Đã xảy ra lỗi khi xóa người dùng',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    public function createUser($request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255|unique:users,email',
                'username' => 'nullable|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/|unique:users,username',
                'password' => 'required|string|min:6|max:255',
                'password_confirmation' => 'required|same:password',
                'role_id' => 'required|integer|exists:roles,id',
                'status' => 'required|string|in:Unconfirmed,Active,Banned',
                'first_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:255',
                'birthday' => 'nullable|date',
                'webhook_url' => 'nullable|url|max:255',
                'telegram_id' => 'nullable|string|max:100',
                'api_key' => 'nullable|string|max:255',
                'max_debit' => 'nullable|numeric|min:0',
                'max_date_debit' => 'nullable|integer|min:0',
                'min_date_debit' => 'nullable|integer|min:0',
                'is_support_us' => 'nullable|boolean',
                'tier_id' => 'nullable|integer|in:0,1,2,3',
                'private_seller' => 'nullable|integer|in:0,1,2,3', // Alias for tier_id
            ]);

            if ($validator->fails()) {
                return [
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'msgCode' => MsgCode::VALIDATION_ERROR,
                    'message' => $validator->errors(),
                ];
            }

            // Không cho tạo admin
            if ($request->role_id == UserRole::ADMIN) {
                return [
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'msgCode' => MsgCode::FORBIDDEN,
                    'message' => 'Không được phép tạo tài khoản với quyền Admin'
                ];
            }

            DB::beginTransaction();

            $user = User::create([
                'email' => $request->email,
                'username' => $request->username ?? $request->email,
                'role_id' => $request->role_id,
                'status' => $request->status,
                'api_key' => $request->api_key,
            ]);

            \App\Models\AuthProvider::create([
                'user_id' => $user->id,
                'provider' => 'local',
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            ]);

            \App\Models\UserProfile::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'birthday' => $request->birthday,
                'webhook_url' => $request->webhook_url,
                'telegram_id' => $request->telegram_id,
                'is_support_us' => $request->is_support_us ?? false,
                'private_seller' => $request->tier_id ?? $request->private_seller ?? 0,
                'max_debit' => $request->max_debit ?? 0,
                'max_date_debit' => $request->max_date_debit ?? 0,
                'min_date_debit' => $request->min_date_debit ?? 0,
                'wallet_balance' => 0,
                'debit_status' => false,
                'production' => false,
            ]);

            DB::commit();

            $user->load(['role', 'profile']);

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'msgCode' => MsgCode::SUCCESS,
                'message' => 'Thêm người dùng thành công',
                'data' => $user
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'msgCode' => MsgCode::SERVER_ERROR,
                'message' => 'Đã xảy ra lỗi khi thêm người dùng',
                'error' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }
}
