<?php

namespace App\Http\Controllers;

use App\Services\DropboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    /**
     * Secret key for authentication (should be in .env)
     */
    protected string $secretKey;

    public function __construct()
    {
        $this->secretKey = env('TELEGRAM_API_KEY', '9I7pbBig1LkE45O3uOWb');
    }

    /**
     * Get current Dropbox access token
     * 
     * Endpoint: GET/POST /api/telegram/getToken?key={SECRET_KEY}
     */
    public function getToken(Request $request): JsonResponse
    {
        $key = $request->input('key');

        if ($key !== $this->secretKey) {
            return response()->json([
                'success' => false,
                'access_token' => '',
                'message' => 'Invalid authentication key'
            ], 401);
        }

        try {
            $tokenPath = public_path('key_driver/tokendropbox.txt');

            if (!file_exists($tokenPath)) {
                return response()->json([
                    'success' => false,
                    'access_token' => '',
                    'message' => 'Token file not found'
                ], 404);
            }

            $accessToken = file_get_contents($tokenPath);

            return response()->json([
                'success' => true,
                'access_token' => trim($accessToken)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Dropbox token', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'access_token' => '',
                'message' => 'Failed to read token',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Refresh Dropbox access token
     * 
     * Endpoint: GET/POST /api/telegram/resetToken?key={SECRET_KEY}
     */
    public function resetToken(Request $request): JsonResponse
    {
        $key = $request->input('key');

        if ($key !== $this->secretKey) {
            return response()->json([
                'success' => false,
                'access_token' => '',
                'message' => 'Invalid authentication key'
            ], 401);
        }

        try {
            $dropboxService = new DropboxService();
            $result = $dropboxService->refresh_token_request();

            if (!$result || !isset($result['access_token'])) {
                Log::error('Dropbox token refresh failed', [
                    'result' => $result
                ]);

                return response()->json([
                    'success' => false,
                    'access_token' => '',
                    'message' => 'Failed to refresh token from Dropbox',
                    'debug' => [
                        'dropbox_response' => $result,
                        'has_refresh_token' => !empty(config('services.dropbox.refresh_token')),
                        'has_app_key' => !empty(config('services.dropbox.app_key')),
                        'has_app_secret' => !empty(config('services.dropbox.app_secret'))
                    ]
                ], 500);
            }

            $accessToken = $result['access_token'];

            // Ensure directory exists
            $keyDirectory = public_path('key_driver');
            if (!is_dir($keyDirectory)) {
                mkdir($keyDirectory, 0755, true);
            }

            // Save new token
            file_put_contents(public_path('key_driver/tokendropbox.txt'), $accessToken);

            Log::info('Dropbox token refreshed successfully', [
                'token_length' => strlen($accessToken),
                'expires_in' => $result['expires_in'] ?? 'N/A'
            ]);

            return response()->json([
                'success' => true,
                'access_token' => $accessToken,
                'expires_in' => $result['expires_in'] ?? null,
                'token_type' => $result['token_type'] ?? 'bearer'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reset Dropbox token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'access_token' => '',
                'message' => 'Failed to refresh token',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
