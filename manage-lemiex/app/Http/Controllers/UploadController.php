<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Allowed file types for different categories
     */
    private const ALLOWED_TYPES = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'design' => ['png'],
    ];

    /**
     * Max file sizes (in bytes)
     */
    private const MAX_SIZES = [
        'image' => 10 * 1024 * 1024,  // 10MB
        'design' => 50 * 1024 * 1024, // 50MB
    ];

    /**
     * Upload a file (image or design file)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadFile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file',
                'type' => 'required|in:mockup,design',
                'order_id' => 'nullable|integer',
                'item_id' => 'nullable|integer',
                // meta_key like "front_pdf", "back_pdf" — identifies which side this upload replaces
                'meta_key' => [
                    'nullable',
                    'string',
                    'max:50',
                    'regex:/^[a-z0-9_]+_pdf$/i',
                    function ($attribute, $value, $fail) use ($request) {
                        if (
                            $request->input('type') === 'design'
                            && $request->filled('order_id')
                            && $request->filled('item_id')
                            && empty($value)
                        ) {
                            $fail('meta_key là bắt buộc khi upload design cho 1 item (vd: front_pdf, back_pdf).');
                        }
                    },
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], HttpCode::BAD_REQUEST);
            }

            $file = $request->file('file');
            $type = $request->input('type');
            $orderId = $request->input('order_id');
            $itemId = $request->input('item_id');
            $metaKey = $request->input('meta_key');

            // Determine category based on type
            $category = $type === 'mockup' ? 'image' : 'design';

            // Validate file extension
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = self::ALLOWED_TYPES[$category];

            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'success' => false,
                    'message' => "Invalid file type. Allowed: " . implode(', ', $allowedExtensions),
                ], HttpCode::BAD_REQUEST);
            }

            // Validate file size
            $maxSize = self::MAX_SIZES[$category];
            if ($file->getSize() > $maxSize) {
                $maxSizeMB = $maxSize / (1024 * 1024);
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'success' => false,
                    'message' => "File too large. Maximum size: {$maxSizeMB}MB",
                ], HttpCode::BAD_REQUEST);
            }

            // Build folder and filename
            if ($type === 'design' && $orderId && $itemId) {
                // Wood order design: use customer-facing filename format (meta_key required, validated above)
                $filename = $this->buildDesignFilename((int) $orderId, (int) $itemId, (string) $metaKey);
            } else {
                // Fallback: legacy random name
                $timestamp = now()->format('Ymd_His');
                $uuid = Str::random(8);
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
                $safeName = substr($safeName, 0, 50);

                $folder = $type === 'mockup' ? 'mockups' : 'pes_files';
                $filename = $orderId
                    ? "{$folder}/{$orderId}_{$safeName}_{$timestamp}_{$uuid}.{$extension}"
                    : "{$folder}/{$safeName}_{$timestamp}_{$uuid}.{$extension}";
            }

            // Upload to B2
            $contents = file_get_contents($file->getRealPath());
            Storage::disk('b2')->put($filename, $contents, 'public');

            // Generate URL
            $url = env('B2_URL', 'https://s3.us-east-005.backblazeb2.com') . '/' . env('B2_BUCKET', 'Lemiex-Fulfillment') . '/' . $filename;

            Log::info('File uploaded successfully', [
                'type' => $type,
                'filename' => $filename,
                'size' => $file->getSize(),
                'url' => $url
            ]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'url' => $url,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'type' => $type,
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'success' => false,
                'message' => 'File upload failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Build customer-facing design filename:
     * merged_image/{order_id}-{item_id}-{product_name}-{sku}-{size}_{meta_key}.png
     * meta_key (e.g. "front_pdf") is required to avoid filename collisions across sides.
     */
    private function buildDesignFilename(int $orderId, int $itemId, string $metaKey): string
    {
        $item = \App\Models\OrderItem::with('productVariant.product')->find($itemId);

        $productName = $item?->productVariant?->product?->name
            ?? $item?->product_name
            ?? 'unknown';
        $sku = $item?->productVariant?->sku ?? 'unknown';
        $size = $item?->productVariant?->size ?? 'unknown';

        $parts = array_map(
            fn (string $p) => $this->sanitizePart($p),
            [(string) $orderId, (string) $itemId, $productName, $sku, $size]
        );
        $safe = substr(implode('-', $parts), 0, 200);

        $safeKey = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $metaKey);

        return "merged_image/{$safe}_{$safeKey}.png";
    }

    /**
     * Sanitize a single filename segment: drop unsafe chars, collapse spaces to underscore,
     * and remove dashes so they only appear as segment separators.
     */
    private function sanitizePart(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        $value = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $value);
        $value = str_replace(' ', '_', $value);
        $value = str_replace('-', '_', $value);
        return $value === '' ? 'unknown' : $value;
    }
}
