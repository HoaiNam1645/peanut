<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\ShipDvxConstants;
use App\Jobs\DownloadBuyLabelPdf;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives async status webhooks from the ShipDVX / DNX buy-label provider.
 *
 * Payload: { orderId, orderNumber, status, data:{...} }
 * Provider retries up to 3x (5 min apart) on non-2xx, 15s timeout — so we
 * respond 200 fast and offload heavy work (label download) to a queue job.
 *
 * `orderNumber` is the value we send in create-orders (our order ref) and is
 * echoed back here, letting us map the provider order to our local order.
 */
class BuyLabelWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // ---- 1. Verify shared secret (if configured) ----
        $expected = config('services.shipdvx.webhook_secret');
        if (!empty($expected)) {
            $provided = $request->header(ShipDvxConstants::HEADER_WEBHOOK_SECRET);
            if (!is_string($provided) || !hash_equals($expected, $provided)) {
                Log::warning('ShipDVX webhook: invalid secret', ['ip' => $request->ip()]);
                return response()->json(['status' => false, 'message' => 'Unauthorized'], HttpCode::FORBIDDEN);
            }
        }

        // ShipDVX may POST with a non-JSON Content-Type (e.g. text/plain), which leaves
        // $request->all() empty (every field then reads as null). Decode the raw body as
        // JSON explicitly — $request->json() decodes regardless of the Content-Type header.
        $payload = $request->json()->all();
        if (empty($payload)) {
            $decoded = json_decode((string) $request->getContent(), true);
            $payload = is_array($decoded) ? $decoded : $request->all();
        }

        // Temporary diagnostic (warning level so it survives LOG_LEVEL=warning): log the
        // raw body + content-type to confirm the provider's exact envelope. Revert to info
        // once the real webhook mapping is confirmed in production.
        Log::warning('ShipDVX webhook raw', [
            'content_type' => $request->header('Content-Type'),
            'body' => mb_substr((string) $request->getContent(), 0, 2000),
        ]);

        $orderNumber = $payload['orderNumber'] ?? ($payload['data']['orderNumber'] ?? null);
        $providerOrderId = $payload['orderId'] ?? ($payload['data']['_id'] ?? null);
        // Envelope is inconsistent: the spec says top-level `status` is the order
        // lifecycle status, but examples carry the webhook result ("SUCCESS") there
        // and the real order status in data.status. Prefer a recognized lifecycle
        // value; otherwise fall back to data.status so GENERATED is never missed.
        $topStatus = $payload['status'] ?? null;
        $dataStatus = $payload['data']['status'] ?? null;
        $status = in_array($topStatus, ShipDvxConstants::STATUSES, true)
            ? $topStatus
            : ($dataStatus ?: $topStatus);
        $data = $payload['data'] ?? [];

        // Temporary: warning level so we can confirm parsed fields under LOG_LEVEL=warning.
        Log::warning('ShipDVX webhook received', [
            'order_number' => $orderNumber,
            'provider_order_id' => $providerOrderId,
            'status' => $status,
        ]);

        // ---- 2. Map to our order ----
        $order = $this->resolveOrder($orderNumber, $providerOrderId);
        if (!$order) {
            // Ack 200 so the provider does not retry forever for an unknown order.
            Log::warning('ShipDVX webhook: order not found', [
                'order_number' => $orderNumber,
                'provider_order_id' => $providerOrderId,
            ]);
            return response()->json(['status' => true, 'message' => 'No matching order']);
        }

        // ---- 3. Idempotency: skip if this exact status already applied + label present ----
        $alreadyGeneratedWithLabel = $status === ShipDvxConstants::STATUS_GENERATED
            && $order->label_status === ShipDvxConstants::STATUS_GENERATED
            && !empty($order->shipping_label);
        if ($alreadyGeneratedWithLabel) {
            Log::info('ShipDVX webhook: GENERATED already processed, skipping', ['order_id' => $order->id]);
            return response()->json(['status' => true, 'message' => 'Already processed']);
        }

        // ---- 4. Persist provider data ----
        $order->provider_order_id = $providerOrderId ?: $order->provider_order_id;
        $order->label_status = $status ?: $order->label_status;
        $order->shipping_json = json_encode($data);

        // shipping partner name (data.shippingPartner may be object or id)
        $partnerName = is_array($data['shippingPartner'] ?? null)
            ? ($data['shippingPartner']['name'] ?? null)
            : null;
        if ($partnerName) {
            $order->shipping_service = $partnerName;
        }

        // barcode / transactionCode -> tracking
        $tracking = $data['barcode'] ?? $data['transactionCode'] ?? null;
        if ($tracking && empty($order->tracking_id)) {
            $order->tracking_id = $tracking;
        }

        $order->save();

        // ---- 5. On GENERATED, fetch + store the label PDF asynchronously ----
        if ($status === ShipDvxConstants::STATUS_GENERATED && $providerOrderId) {
            DownloadBuyLabelPdf::dispatch($order->id, (string) $providerOrderId)->onQueue('default');
            Log::info('ShipDVX webhook: dispatched label download', [
                'order_id' => $order->id,
                'provider_order_id' => $providerOrderId,
            ]);
        }

        if (in_array($status, ShipDvxConstants::FAILURE_STATUSES, true)) {
            Log::error('ShipDVX webhook: order failed', [
                'order_id' => $order->id,
                'status' => $status,
                'data' => $data,
            ]);
        }

        return response()->json(['status' => true]);
    }

    /**
     * Resolve our order from the provider's orderNumber (preferred) or provider order id.
     */
    private function resolveOrder($orderNumber, $providerOrderId): ?Order
    {
        if ($providerOrderId) {
            $byProviderId = Order::where('provider_order_id', $providerOrderId)->first();
            if ($byProviderId) {
                return $byProviderId;
            }
        }

        if ($orderNumber) {
            $byProviderNumber = Order::where('provider_order_number', $orderNumber)->first();
            if ($byProviderNumber) {
                return $byProviderNumber;
            }
            // Fallback: orderNumber may equal our ref_id (set when create-orders is wired)
            return Order::where('ref_id', $orderNumber)->first();
        }

        return null;
    }
}
