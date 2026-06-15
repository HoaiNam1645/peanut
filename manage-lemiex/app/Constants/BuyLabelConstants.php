<?php

namespace App\Constants;

class BuyLabelConstants
{
    // Success messages
    const LABEL_CREATED_SUCCESS = 'Buy label successfully';
    const LABELS_DISPATCHED_SUCCESS = 'Buy label jobs dispatched successfully';
    const ELIGIBLE_ORDERS_RETRIEVED = 'Eligible orders retrieved successfully';

    // Error messages
    const ORDER_NOT_FOUND = 'Order not found';
    const UNAUTHORIZED = 'Unauthorized';
    const NO_PRODUCTION_PERMISSION = 'Seller does not have production permission';
    const LABEL_ALREADY_EXISTS = 'Label was already bought';
    const TRACKING_ALREADY_EXISTS = 'Tracking already exists';
    const EMPTY_ADDRESS = 'Empty shipping address';
    const SOME_ORDERS_UNAUTHORIZED = 'Some orders not found or unauthorized';
    const LABEL_CREATION_FAILED = 'Buy error';
    const BATCH_DISPATCH_FAILED = 'Batch buy error';
    const CHECK_ELIGIBLE_FAILED = 'Failed to check orders';

    // Validation messages
    const INVALID_ORDER_ID = 'Invalid order ID';
    const INVALID_ORDER_IDS = 'Invalid order IDs';

    // Eligibility reasons
    const REASON_LABEL_EXISTS = 'Label already exists';
    const REASON_TRACKING_EXISTS = 'Tracking already exists';
    const REASON_NO_ADDRESS = 'No shipping address';
    const REASON_NO_PERMISSION = 'No production permission';
    const REASON_PRODUCING = 'Order is producing';

    // Fields
    const FIELD_ORDER_ID = 'order_id';
    const FIELD_TRACKING_NUMBER = 'tracking_number';
    const FIELD_LABEL_URL = 'label_url';
    const FIELD_SHIPPING_SERVICE = 'shipping_service';
    const FIELD_TOTAL_ORDERS = 'total_orders';
    const FIELD_DISPATCHED = 'dispatched';
    const FIELD_ELIGIBLE = 'eligible';
    const FIELD_INELIGIBLE = 'ineligible';
    const FIELD_TOTAL_ELIGIBLE = 'total_eligible';
    const FIELD_TOTAL_INELIGIBLE = 'total_ineligible';
    const FIELD_REF_ID = 'ref_id';
    const FIELD_REASONS = 'reasons';

    // Shipping service
    const SHIPPING_SERVICE_USPS = 'USPS';

    // Service codes
    const SERVICE_GROUND_ADVANTAGE = 'usps_ground_advantage';
    const SERVICE_PRIORITY_MAIL = 'usps_priority_mail';

    // Shipping methods
    const METHOD_STANDARD = 'standard';
    const METHOD_PRIORITY = 'priority';

    // Timeline
    const TIMELINE_OBJECT_ORDER = 'order';
    const TIMELINE_ACTION_BUY_LABEL = 'Buy label';
    const TIMELINE_NOTE_AUTO = 'Auto buy label with ShipEngine for order %d';
    const TIMELINE_NOTE_MANUAL = '%s buy label with ShipEngine';

    // Default values
    const DEFAULT_WEIGHT_OZ = 8;
    const DEFAULT_PHONE = '555-555-5555';
    const DEFAULT_CUSTOMER_NAME = 'Customer';

    // Package dimensions
    const PACKAGE_LENGTH = 15;
    const PACKAGE_WIDTH = 14;
    const PACKAGE_HEIGHT_SINGLE = 2;
    const PACKAGE_HEIGHT_MULTIPLE = 4;
    const PACKAGE_UNIT = 'inch';

    // Weight
    const WEIGHT_UNIT_POUND = 'pound';
    const OZ_TO_POUND_RATIO = 16;
}
