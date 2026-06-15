<?php

namespace App\Constants;

class OrderConstants
{
    // Pagination
    const DEFAULT_PER_PAGE = 20;
    const DEFAULT_PAGE = 1;

    // Sorting
    const DEFAULT_SORT_BY = 'created_at';
    const DEFAULT_SORT_ORDER = 'asc';

    // Ship-out batch reconciliation.
    // The workshop ships one batch per day around noon. A "ship day" therefore runs
    // from the previous day's cutoff to the selected day's cutoff (e.g. ship-day
    // 12/06 = orders scanned shipped between 11/06 12:00 and 12/06 12:00 local time),
    // so the cross-midnight production batch ("afternoon + next morning") stays whole.
    // Timestamps are stored in UTC, so filters convert these local boundaries to UTC.
    const SHIP_BUSINESS_TIMEZONE = 'Asia/Ho_Chi_Minh';
    const SHIP_CUTOFF_HOUR = 12; // local noon

    // Cache settings
    const FAILED_AUTH_CACHE_TTL = 300; // 5 minutes
    const API_KEY_LOCK_TTL = 900; // 15 minutes
    const MAX_FAILED_AUTH_ATTEMPTS = 5;

    // Calculation percentages
    const SELLER_PAYOUT_PERCENTAGE = 0.7; // 70%
    const PLATFORM_FEE_PERCENTAGE = 0.3; // 30%
    const SELLER_PROFIT_PERCENTAGE = 0.35; // 35%
    const ESTIMATED_COST_PERCENTAGE = 0.6; // 60%

    // Delivery estimates (in days)
    const PRODUCTION_DEADLINE_DAYS = 3;
    const ESTIMATED_DELIVERY_DAYS = 7;
    const OVERDUE_THRESHOLD_DAYS = 5;

    // Default values
    const DEFAULT_EXTRA_FEE = 0.00;
    const DEFAULT_REFUND_FEE = 0.00;
    const DEFAULT_PRIORITY_LEVEL = 'normal';
    const HIGH_VALUE_THRESHOLD = 100;

    // Order types
    const ORDER_TYPE_NO_DESIGN = 'no_design';
    const ORDER_TYPE_SELLER_SHIP = 'seller_ship';
    const ORDER_TYPE_LABEL_SHIP = 'label_ship';

    // Address placeholder
    const ADDRESS_PLACEHOLDER = '*';

    // Design file positions
    const DESIGN_POSITION_FRONT = 'front';
    const DESIGN_POSITION_BACK = 'back';

    // Design file types
    const DESIGN_FILE_PDF = 'pdf';
    const DESIGN_FILE_DST = 'dst';
    const DESIGN_FILE_EMB = 'emb';
    const DESIGN_FILE_PES = 'pes';

    // Date range labels
    const DATE_RANGE_ALL = 'all';
    const DATE_RANGE_TODAY = 'today';
    const DATE_RANGE_LAST_7_DAYS = 'last_7_days';
    const DATE_RANGE_LAST_30_DAYS = 'last_30_days';
    const DATE_RANGE_CUSTOM = 'custom';

    // Time periods
    const PERIOD_TODAY = 'today';
    const PERIOD_WEEK = 'week';
    const PERIOD_MONTH = 'month';
    const PERIOD_LAST_MONTH = 'last_month';
}
