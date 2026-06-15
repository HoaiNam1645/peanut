<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Models\PartnerApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerAppController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $apps = PartnerApp::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'auth_url', 'proxy_status', 'status', 'created_at', 'updated_at']);

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Partner apps retrieved successfully',
            'data' => $apps,
        ], HttpCode::SUCCESS);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:partner_apps,slug',
            'auth_url' => 'nullable|string|max:2048',
            'proxy_status' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ]);

        $app = PartnerApp::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'auth_url' => $validated['auth_url'] ?? null,
            'proxy_status' => $validated['proxy_status'] ?? 'live',
            'status' => $validated['status'] ?? 'Active',
        ]);

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Partner app created successfully',
            'data' => $app,
        ], HttpCode::SUCCESS);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $app = PartnerApp::find($id);

        if (!$app) {
            return response()->json([
                'code' => HttpCode::NOT_FOUND,
                'status' => false,
                'message' => 'Partner app not found',
            ], HttpCode::NOT_FOUND);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:partner_apps,slug,' . $id,
            'auth_url' => 'nullable|string|max:2048',
            'proxy_status' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ]);

        $app->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'auth_url' => $validated['auth_url'] ?? null,
            'proxy_status' => $validated['proxy_status'] ?? $app->proxy_status,
            'status' => $validated['status'] ?? $app->status,
        ]);

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Partner app updated successfully',
            'data' => $app->fresh(),
        ], HttpCode::SUCCESS);
    }
}
