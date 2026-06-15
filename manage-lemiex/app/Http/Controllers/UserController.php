<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\MsgCode;
use App\Models\Role;
use App\Services\UserServices;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userServices;

    public function __construct(UserServices $userServices)
    {
        $this->userServices = $userServices;
    }

    public function index(Request $request)
    {
        $result = $this->userServices->getUsers($request);
        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function register(Request $request)
    {
        $result = $this->userServices->createUser($request);
        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function show($id)
    {
        $result = $this->userServices->getUserById($id);
        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function update(Request $request, $id)
    {
        $result = $this->userServices->updateUser($request, $id);
        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy($id)
    {
        $result = $this->userServices->deleteUser($id);
        return response()->json($result, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get all roles for dropdown
     */
    public function getRoles()
    {
        $roles = Role::select('id', 'name', 'display_name', 'description')
            ->orderBy('id')
            ->get();

        return response()->json([
            'code' => 200,
            'status' => true,
            'message' => 'Get roles successfully',
            'data' => $roles
        ], 200);
    }
}
