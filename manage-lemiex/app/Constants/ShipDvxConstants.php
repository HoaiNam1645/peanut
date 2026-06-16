<?php

namespace App\Constants;

/**
 * Constants for the ShipDVX / DNX Logistics buy-label provider.
 * API doc: see /api-buy-label.txt
 */
class ShipDvxConstants
{
    // ---- API endpoints (relative to services.shipdvx.domain) ----
    const EP_CREATE_ORDERS    = '/v1/partner/create-orders';
    const EP_PREVIEW_PRICES   = '/v1/partner/orders/preview-prices';
    const EP_ORDERS           = '/v1/partner/orders';                 // GET list (?page=&limit=)
    const EP_ORDER_DETAIL     = '/v1/partner/orders/%s';              // GET {id}
    const EP_ORDER_CANCEL     = '/v1/partner/orders/%s/cancel';       // POST {id}
    const EP_ORDER_LABEL      = '/v1/partner/orders/%s/label';        // GET {id} -> PDF buffer
    const EP_SHIPPING_PARTNERS = '/v1/partner/shipping-partners';     // GET
    const EP_SETUP            = '/v1/partner/setup';                  // PUT {webhookURL}

    // ---- Auth / headers ----
    const HEADER_API_KEY        = 'x-api-key';
    const HEADER_WEBHOOK_SECRET = 'X-Webhook-Secret';

    // ---- Order status lifecycle (from webhook) ----
    const STATUS_PENDING      = 'PENDING';
    const STATUS_GENERATED    = 'GENERATED';
    const STATUS_SCANNED      = 'SCANNED';
    const STATUS_PROCESSING   = 'PROCESSING';
    const STATUS_PROCESSED    = 'PROCESSED';
    const STATUS_DELIVERED    = 'DELIVERED';
    const STATUS_WARNING      = 'WARNING';
    const STATUS_FAILED       = 'FAILED';
    const STATUS_ORDER_FAILED = 'ORDER_FAILED';
    const STATUS_CANCELLED    = 'CANCELLED';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_GENERATED,
        self::STATUS_SCANNED,
        self::STATUS_PROCESSING,
        self::STATUS_PROCESSED,
        self::STATUS_DELIVERED,
        self::STATUS_WARNING,
        self::STATUS_FAILED,
        self::STATUS_ORDER_FAILED,
        self::STATUS_CANCELLED,
    ];

    // Status at which the label PDF is available for download
    const STATUS_LABEL_READY = self::STATUS_GENERATED;

    // Terminal failure statuses
    const FAILURE_STATUSES = [
        self::STATUS_FAILED,
        self::STATUS_ORDER_FAILED,
    ];

    // ---- Shipping partners (by name; ids fetched via EP_SHIPPING_PARTNERS) ----
    const PARTNER_USPS      = 'USPS';
    const PARTNER_NON_US    = 'NON-US';
    const PARTNER_REMOTE_US = 'REMOTE-US';
    const PARTNER_TIKTOK    = 'TIKTOK';
    const PARTNER_DHL       = 'DHL';

    // ---- Fallbacks when SKU data not yet provided by the workshop ----
    const DEFAULT_ITEM_WEIGHT_G = 200;   // gram per item
    const DEFAULT_ITEM_VALUE_USD = 5;    // USD customs value per item

    // ---- HTTP ----
    const REQUEST_TIMEOUT = 30; // seconds
}
