<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WoodMergeImageService
{
    public function generateForOrder(Order $order): void
    {
        $items = OrderItem::with('productVariant.product')
            ->where('order_id', $order->id)
            ->get();

        foreach ($items as $item) {
            try {
                $this->generateForItem($order, $item);
            } catch (Exception $e) {
                Log::error('Wood merge image failed for item', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Regenerate merge_image only for the given item IDs of an order.
     * Used on update flow when print_files of specific items change.
     */
    public function regenerateForItems(Order $order, array $itemIds): void
    {
        if (empty($itemIds)) {
            return;
        }

        $items = OrderItem::with('productVariant.product')
            ->where('order_id', $order->id)
            ->whereIn('id', $itemIds)
            ->get();

        foreach ($items as $item) {
            try {
                $this->generateForItem($order, $item);
            } catch (Exception $e) {
                Log::error('Wood merge image regenerate failed', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function generateForItem(Order $order, OrderItem $item): void
    {
        $pdfMetas = OrderItemMeta::where('order_item_id', $item->id)
            ->where('meta_key', 'like', '%_pdf')
            ->get();

        if ($pdfMetas->isEmpty()) {
            Log::info('Wood merge skipped: no design pdf meta', [
                'order_id' => $order->id,
                'item_id' => $item->id,
            ]);
            return;
        }

        $variant = $item->productVariant;
        $product = $variant?->product;

        $productName = $product->name ?? $item->product_name ?? 'unknown';
        $sku = $variant->sku ?? 'unknown';
        $size = $variant->size ?? 'unknown';

        $rawName = "{$order->id} - {$item->id} - {$productName} - {$sku} - {$size}";
        $safeName = $this->sanitizeFilename($rawName);

        OrderItemMeta::where('order_item_id', $item->id)
            ->where('meta_key', 'merge_image')
            ->delete();

        foreach ($pdfMetas as $meta) {
            $sourceUrl = $meta->meta_value;
            if (empty($sourceUrl)) {
                continue;
            }

            $suffix = count($pdfMetas) > 1
                ? '_' . str_replace('_pdf', '', $meta->meta_key)
                : '';
            $filename = "merged_image/{$safeName}{$suffix}.png";

            $url = $this->copyToB2($sourceUrl, $filename);
            if (!$url) {
                continue;
            }

            OrderItemMeta::create([
                'order_item_id' => $item->id,
                'meta_key' => 'merge_image',
                'meta_value' => $url,
                'switch' => 0,
                'status' => false,
            ]);

            Log::info('Wood merge image uploaded', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'url' => $url,
            ]);
        }
    }

    protected function copyToB2(string $sourceUrl, string $key): ?string
    {
        // Google Drive share/view links return an HTML preview page, not the file.
        // Convert to a direct-download URL so the GET fetches real bytes. Also covers
        // legacy metas saved before the create/update flow started converting.
        $sourceUrl = app(\App\Services\PushFileJsonToBackblazeService::class)
            ->toDirectDownloadUrl($sourceUrl);

        $response = Http::timeout(60)
            ->withOptions(['verify' => config('app.http_verify_ssl', true)])
            ->get($sourceUrl);

        if (!$response->successful()) {
            Log::warning('Wood merge: failed to download source', [
                'url' => $sourceUrl,
                'status' => $response->status(),
            ]);
            return null;
        }

        $pngBytes = $this->convertToPng($response->body());
        if ($pngBytes === null) {
            Log::warning('Wood merge: failed to convert source to PNG', [
                'url' => $sourceUrl,
            ]);
            return null;
        }

        Storage::disk('b2')->put($key, $pngBytes, [
            'visibility' => 'public',
            'ContentType' => 'image/png',
        ]);

        $b2Url = env('B2_URL', 'https://s3.us-east-005.backblazeb2.com');
        $bucket = env('B2_BUCKET', 'Lemiex-Fulfillment');
        return "{$b2Url}/{$bucket}/{$key}";
    }

    protected function convertToPng(string $bytes): ?string
    {
        // Prefer Imagick (handles more formats, including PDF if delegate present)
        if (extension_loaded('imagick')) {
            try {
                $img = new \Imagick();
                $img->readImageBlob($bytes);
                $img->setImageFormat('png');
                $out = $img->getImageBlob();
                $img->clear();
                return $out;
            } catch (\Throwable $e) {
                Log::warning('Wood merge: Imagick convert failed, falling back', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // GD fallback (jpg/png/gif/webp/bmp)
        if (extension_loaded('gd')) {
            $im = @imagecreatefromstring($bytes);
            if ($im === false) {
                return null;
            }
            // Preserve transparency
            imagesavealpha($im, true);
            $buffer = null;
            ob_start();
            try {
                imagepng($im);
                $buffer = ob_get_clean();
            } finally {
                imagedestroy($im);
            }
            return $buffer ?: null;
        }

        return null;
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name));
        $name = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $name);
        $name = str_replace(' ', '_', $name);
        return substr($name, 0, 200);
    }
}
