<?php

namespace App\Constants;

class ResponseMessage
{
    // Success messages
    const SUCCESS = '';
    const CREATED_SUCCESS = 'Created successfully';
    const UPDATED_SUCCESS = 'Updated successfully';
    const DELETED_SUCCESS = 'Deleted successfully';

    // Product messages
    const PRODUCTS_RETRIEVED = 'Products retrieved successfully';
    const PRODUCT_VARIANTS_RETRIEVED = 'Product variants retrieved successfully';
    const PRODUCT_VARIANT_RETRIEVED = 'Product variant retrieved successfully';
    const PRODUCT_VARIANT_NOT_FOUND = 'Product variant not found';
    const COLORS_RETRIEVED_FAILED = 'Failed to retrieve colors';
    const SIZES_RETRIEVED_FAILED = 'Failed to retrieve sizes';
    const PRODUCTS_RETRIEVED_FAILED = 'Failed to retrieve products';
    const PRODUCT_VARIANTS_RETRIEVED_FAILED = 'Failed to retrieve product variants';
    const PRODUCT_VARIANT_RETRIEVED_FAILED = 'Failed to retrieve product variant';

    // Order messages
    const ORDER_CREATED = 'Order created successfully';
    const ORDER_CREATION_FAILED = 'Failed to create order';
    const ORDER_ALREADY_EXISTS = 'Order with this ref_id already exists. Please use a different ref_id or check existing order.';
    const ORDERS_RETRIEVED = 'Orders retrieved successfully';
    const ORDERS_RETRIEVED_FAILED = 'Failed to get orders';
    const ORDER_NOT_FOUND = 'Order not found';
    const ORDER_RETRIEVED_FAILED = 'Failed to get order';
    const ORDER_TIMELINE_RETRIEVED_FAILED = 'Failed to get order timeline';
    const ORDER_PERMISSION_DENIED = 'You do not have permission to view this order';
    const ORDER_TIMELINE_PERMISSION_DENIED = 'You do not have permission to view this order timeline';
    const ORDER_TRACK_FAILED = 'Failed to track order';
    const ITEM_NOT_FOUND = 'Item not found';

    // Order type messages
    const INVALID_ORDER_TYPE = 'Invalid order type. Must be: no_design, seller_ship, or label_ship';

    // Order status messages
    const ORDER_STATUS_CHANGED = 'Order status changed successfully';
    const ORDER_STATUS_CHANGE_FAILED = 'Failed to change order status';
    const INVALID_STATUS_TRANSITION = 'Invalid status transition';

    // Stock messages
    const STOCK_SUMMARY_RETRIEVED = 'Stock summary retrieved successfully';
    const STOCK_SUMMARY_RETRIEVED_FAILED = 'Failed to retrieve stock summary';
    const STOCK_LIST_RETRIEVED = 'Stock list retrieved successfully';
    const STOCK_LIST_RETRIEVED_FAILED = 'Failed to retrieve stock list';
    const STOCK_FILTER_OPTIONS_RETRIEVED = 'Filter options retrieved successfully';
    const STOCK_FILTER_OPTIONS_RETRIEVED_FAILED = 'Failed to retrieve filter options';
    const VARIANT_UPDATED = 'Variant updated successfully';
    const VARIANT_UPDATE_FAILED = 'Failed to update variant';
    const VARIANT_NOT_FOUND = 'Variant not found';
    const VARIANT_HISTORY_RETRIEVED = 'Variant history retrieved successfully';
    const VARIANT_HISTORY_RETRIEVED_FAILED = 'Failed to retrieve variant history';
    const VARIANTS_BULK_UPDATED = 'Variants updated successfully';
    const VARIANTS_BULK_UPDATE_FAILED = 'Failed to update variants';
    const VALIDATION_FAILED = 'Validation failed';
    const IMPORT_STOCK_FAILED = 'The quantity change was unsuccessful';

    // Label messages
    const LABEL_UPDATED = 'Label updated successfully';
    const LABEL_UPDATE_FAILED = 'Failed to update label';
    const LABEL_POST_SUCCESS = 'Label posted successfully';
    const LABEL_POST_FAILED = 'Failed to post label';
    const LABEL_NO_CHANGES = 'No changes to the order';
    const LABEL_IS_FEDEX = 'Label is FedEx';
    const LABEL_IS_UPS = 'Label is UPS';

    // Remake messages
    const REMAKE_SUCCESS = 'PES files remade successfully';
    const REMAKE_FAILED = 'Failed to remake files';
    const REMAKE_NO_DESIGN_FILES = 'No design files (front, back, sleeve...) found in selected metas';
    const REMAKE_NO_PES_FILES = 'Selected metas do not have PES files to convert';
    const REMAKE_CONVERT_ERROR = 'Error from conversion service';
    const REMAKE_QR_SUCCESS = 'QR codes remade successfully';
    const REMAKE_QR_FAILED = 'Failed to remake QR codes';
}
