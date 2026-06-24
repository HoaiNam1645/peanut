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
    const STATUS_ERROR        = 'ERROR';      // provider rejected the order (reason in webhook `error`)

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
        self::STATUS_ERROR,
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
    // Default package dims (cm) when a variant has no stored dimensions. The provider
    // rejects 0 (min 0.01); these approximate a folded-garment poly mailer.
    const DEFAULT_ITEM_LENGTH_CM = 20;
    const DEFAULT_ITEM_WIDTH_CM  = 15;
    const DEFAULT_ITEM_HEIGHT_CM = 2;

    // ShipDVX item text limits. preview-prices is lenient, but create-orders
    // enforces them (longer → generic "orders validation error" on the sync call,
    // or an ORDER_FAILED webhook on the async job):
    //   - name: long marketing titles (~115) fail; keep a short customs descriptor.
    //   - description (itemDescription): hard max 50 chars (ORDER_FAILED:
    //     "itemDescription must NOT have more than 50 characters") for buy orders.
    const MAX_ITEM_NAME_LEN = 60;
    const MAX_ITEM_DESC_LEN = 50;

    // US state/territory full name (UPPERCASE) → 2-letter code. The provider requires
    // recipient.state to be exactly 2 chars for US, so we normalize full names here.
    const US_STATE_CODES = [
        'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
        'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
        'DISTRICT OF COLUMBIA' => 'DC', 'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI',
        'IDAHO' => 'ID', 'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA',
        'KANSAS' => 'KS', 'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME',
        'MARYLAND' => 'MD', 'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN',
        'MISSISSIPPI' => 'MS', 'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE',
        'NEVADA' => 'NV', 'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM',
        'NEW YORK' => 'NY', 'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH',
        'OKLAHOMA' => 'OK', 'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI',
        'SOUTH CAROLINA' => 'SC', 'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX',
        'UTAH' => 'UT', 'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA',
        'WEST VIRGINIA' => 'WV', 'WISCONSIN' => 'WI', 'WYOMING' => 'WY',
        'PUERTO RICO' => 'PR', 'GUAM' => 'GU', 'VIRGIN ISLANDS' => 'VI',
        'AMERICAN SAMOA' => 'AS', 'NORTHERN MARIANA ISLANDS' => 'MP',
    ];

    // ---- HTTP ----
    const REQUEST_TIMEOUT = 30; // seconds
}
