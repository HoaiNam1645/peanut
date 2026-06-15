<?php

namespace App\Services;

use App\Constants\HttpCode;
use App\Constants\MsgCode;
use App\Enums\UserRole;
use App\Models\AuthProvider;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthServices
{
    protected $userModel;
    public function __construct(User $user)
    {
        $this->userModel = $user;
    }

    public function login($request)
    {
        // Get login identifier (can be email or username)
        $loginId = $request->email ?? $request->login;
        $password = $request->password;

        $validateResult = $this->validateLoginCredentials($loginId, $password);
        if (!$validateResult['isValid']) {
            return $validateResult['response'];
        }

        $authResult = $this->authenticateUser($loginId, $password);
        if (!$authResult['isValid']) {
            return $authResult['response'];
        }

        $tokenData = $this->generateTokenAndCookie($authResult['token']);

        return [
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'msgCode' => MsgCode::LOGIN_SUCCESS,
            'message' => 'Đăng nhập thành công',
            'data' => [
                'token' => $tokenData['token'],
                'user' => $authResult['user'],
            ],
            'cookie' => $tokenData['cookie']
        ];
    }

    private function validateLoginCredentials($loginId, $password)
    {
        // Check if loginId is email format
        $isEmail = filter_var($loginId, FILTER_VALIDATE_EMAIL);

        $rules = [
            'login' => $isEmail ? 'required|email' : 'required|min:3',
            'password' => 'required|min:6',
        ];

        $messages = [
            'login.required' => 'Vui lòng nhập email hoặc username.',
            'login.email' => 'Email không đúng định dạng.',
            'login.min' => 'Username phải có ít nhất :min ký tự.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
        ];

        $validator = Validator::make([
            'login' => $loginId,
            'password' => $password,
        ], $rules, $messages);

        if ($validator->fails()) {
            return [
                'isValid' => false,
                'response' => [
                    'code' => HttpCode::VALIDATION_ERROR,
                    'status' => false,
                    'msgCode' => MsgCode::VALIDATION_ERROR,
                    'message' => $validator->errors(),
                ]
            ];
        }

        return ['isValid' => true, 'data' => $validator->validated()];
    }

    private function authenticateUser($loginId, $password)
    {
        // Try to find user by email or username
        $user = $this->userModel->getUserByEmailOrUsername($loginId);

        if (!$user) {
            return [
                'isValid' => false,
                'response' => [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'msgCode' => MsgCode::NOT_FOUND,
                    'message' => 'Email/Username hoặc mật khẩu không đúng'
                ]
            ];
        }

        $authProvider = $this->userModel->getProviderByUser($user->id);
        if (!$authProvider || !Hash::check($password, $authProvider->password)) {
            return [
                'isValid' => false,
                'response' => [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'msgCode' => MsgCode::NOT_FOUND,
                    'message' => 'Email/Username hoặc mật khẩu không đúng'
                ]
            ];
        }
        $token = JWTAuth::fromUser($user);

        return [
            'isValid' => true,
            'user' => $user,
            'token' => $token
        ];
    }

    private function generateTokenAndCookie($token)
    {
        // Check if using HTTPS (not just production env)
        $isHttps = str_starts_with(config('app.url'), 'https://');

        $cookie = cookie(
            'token',               // name
            $token,                // value
            (int) config('jwt.ttl'),     // minutes (from JWT config) - cast to int
            '/',                   // path
            null,                  // domain (null = current domain)
            $isHttps,              // secure - only true if using HTTPS
            true,                  // httpOnly (prevent XSS)
            false,                 // raw
            'lax'                  // sameSite - 'lax' is more compatible
        );

        return [
            'token' => $token,
            'cookie' => $cookie
        ];
    }
}
