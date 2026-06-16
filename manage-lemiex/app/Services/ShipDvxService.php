<?php

namespace App\Services;

use App\Constants\ShipDvxConstants;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for the ShipDVX / DNX Logistics buy-label provider.
 *
 * Async model: createOrders() returns a jobId immediately; the actual label /
 * tracking arrive later via webhook (see BuyLabelWebhookController). Use
 * downloadLabel() once an order reaches GENERATED to fetch the PDF.
 *
 * API doc: /api-buy-label.txt
 */
class ShipDvxService
{
    private string $domain;
    private ?string $apiKey;

    public function __construct()
    {
        $this->domain = rtrim((string) config('services.shipdvx.domain'), '/');
        $this->apiKey = config('services.shipdvx.api_key');
    }

    /**
     * Whether the provider is configured (domain + api key present).
     */
    public function isConfigured(): bool
    {
        return !empty($this->domain) && !empty($this->apiKey);
    }

    private function client(): PendingRequest
    {
        if (empty($this->apiKey)) {
            throw new Exception('ShipDVX API key is not configured (SHIPDVX_API_KEY).');
        }

        return Http::withHeaders([
            ShipDvxConstants::HEADER_API_KEY => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(ShipDvxConstants::REQUEST_TIMEOUT)
            ->baseUrl($this->domain);
    }

    /**
     * BUY LABEL — create one or more orders (async).
     * @param array $orders array of order payloads (recipient, weight, dims, shippingPartner, items[])
     * @return array provider `data` -> ['success' => bool, 'jobId' => string]
     */
    public function createOrders(array $orders): array
    {
        $response = $this->client()->post(ShipDvxConstants::EP_CREATE_ORDERS, [
            'orders' => array_values($orders),
        ]);

        $this->logResponse('create-orders', $response, ['order_count' => count($orders)]);

        if (!$response->successful()) {
            throw new Exception('ShipDVX create-orders failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * PREVIEW PRICE for one or more orders (does not create them).
     * @return array list of [{index, calculatedPrice, chargeableWeight, shippingPartner, isZone9}]
     */
    public function previewPrices(array $orders): array
    {
        $response = $this->client()->post(ShipDvxConstants::EP_PREVIEW_PRICES, [
            'orders' => array_values($orders),
        ]);

        $this->logResponse('preview-prices', $response, ['order_count' => count($orders)]);

        if (!$response->successful()) {
            throw new Exception('ShipDVX preview-prices failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * GET paginated orders.
     */
    public function getOrders(int $page = 1, int $limit = 20): array
    {
        $response = $this->client()->get(ShipDvxConstants::EP_ORDERS, [
            'page' => $page,
            'limit' => $limit,
        ]);

        if (!$response->successful()) {
            throw new Exception('ShipDVX get-orders failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * GET single order detail by provider order id.
     */
    public function getOrderDetail(string $orderId): array
    {
        $response = $this->client()->get(sprintf(ShipDvxConstants::EP_ORDER_DETAIL, $orderId));

        if (!$response->successful()) {
            throw new Exception('ShipDVX get-order-detail failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * CANCEL an order (refund handled provider-side).
     * @return array ['message', 'orderNumber', 'refunded', 'refundAmount', 'refundPercentage', 'previousStatus']
     */
    public function cancelOrder(string $orderId): array
    {
        $response = $this->client()->post(sprintf(ShipDvxConstants::EP_ORDER_CANCEL, $orderId));

        $this->logResponse('cancel-order', $response, ['provider_order_id' => $orderId]);

        if (!$response->successful()) {
            throw new Exception('ShipDVX cancel-order failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * DOWNLOAD the label PDF for a generated order.
     * @return string|null raw PDF bytes, or null on failure
     */
    public function downloadLabel(string $orderId): ?string
    {
        $response = Http::withHeaders([
            ShipDvxConstants::HEADER_API_KEY => $this->apiKey,
        ])
            ->timeout(ShipDvxConstants::REQUEST_TIMEOUT)
            ->baseUrl($this->domain)
            ->get(sprintf(ShipDvxConstants::EP_ORDER_LABEL, $orderId));

        if (!$response->successful()) {
            Log::error('ShipDVX download-label failed', [
                'provider_order_id' => $orderId,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $response->body();
    }

    /**
     * GET active shipping partners.
     */
    public function getShippingPartners(): array
    {
        $response = $this->client()->get(ShipDvxConstants::EP_SHIPPING_PARTNERS);

        if (!$response->successful()) {
            throw new Exception('ShipDVX shipping-partners failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Resolve a shipping-partner id by its name (e.g. "USPS"). Null if not found.
     */
    public function getShippingPartnerIdByName(string $name): ?string
    {
        foreach ($this->getShippingPartners() as $partner) {
            if (isset($partner['name']) && strcasecmp($partner['name'], $name) === 0) {
                return $partner['id'] ?? $partner['_id'] ?? null;
            }
        }

        return null;
    }

    /**
     * Register our webhook URL with the provider (PUT /v1/partner/setup).
     */
    public function setupWebhook(string $webhookUrl): array
    {
        $response = $this->client()->put(ShipDvxConstants::EP_SETUP, [
            'webhookURL' => $webhookUrl,
        ]);

        $this->logResponse('setup-webhook', $response, ['webhook_url' => $webhookUrl]);

        if (!$response->successful()) {
            throw new Exception('ShipDVX setup-webhook failed: ' . $response->status() . ' ' . $response->body());
        }

        return $response->json('data') ?? [];
    }

    private function logResponse(string $action, $response, array $context = []): void
    {
        Log::info("ShipDVX {$action}", array_merge($context, [
            'status' => $response->status(),
            'ok' => $response->successful(),
            'body' => mb_substr((string) $response->body(), 0, 1000),
        ]));
    }
}
