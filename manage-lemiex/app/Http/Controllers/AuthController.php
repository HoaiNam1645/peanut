<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\MsgCode;
use App\Services\AuthServices;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $authServices;
    public function __construct(AuthServices $auth)
    {
        $this->authServices = $auth;
    }

    public function login(Request $request)
    {
        $result = $this->authServices->login($request);
        if (isset($result['cookie'])) {
            $cookie = $result['cookie'];
            unset($result['cookie']);
            return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE)->cookie($cookie);
        }
        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function logout()
    {
        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'msgCode' => MsgCode::LOGIN_SUCCESS,
            'message' => 'Đăng xuất thành công',
        ], 200, [], JSON_UNESCAPED_UNICODE)->withoutCookie('token');
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $user->load('role.permissions');
        $user->load('profile');
        $user->load('stores');

        $userData = $user->toArray();

        // Only admin can see api_key
        if ($user->role_id == 1) {
            $userData['api_key'] = $user->api_key ?: 'N/A';
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'msgCode' => MsgCode::SUCCESS,
            'message' => 'Lấy thông tin user thành công',
            'data' => $userData
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
