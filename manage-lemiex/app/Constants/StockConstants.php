<?php

namespace App\Constants;

class StockConstants
{
    // Pagination
    const DEFAULT_PER_PAGE = 50;
    
    // Stock levels
    const LOW_STOCK_THRESHOLD = 5;
    const OUT_OF_STOCK = 0;
    
    // Stock level filters
    const STOCK_LEVEL_ALL = 'all';
    const STOCK_LEVEL_LOW = 'low';
    const STOCK_LEVEL_OUT = 'out';
    
    // Active status filters
    const ACTIVE_STATUS_ALL = 'all';
    const ACTIVE_STATUS_ACTIVE = 'active';
    const ACTIVE_STATUS_INACTIVE = 'inactive';
    
    // Bulk actions
    const BULK_ACTION_ACTIVATE = 'activate';
    const BULK_ACTION_DEACTIVATE = 'deactivate';
    const BULK_ACTION_ADD_STOCK = 'add_stock';
    const BULK_ACTION_SUBTRACT_STOCK = 'subtract_stock';
    const BULK_ACTION_SET_STOCK = 'set_stock';
    
    // Stock audit actions
    const AUDIT_ACTION_INCREASE = 'increase';
    const AUDIT_ACTION_DECREASE = 'decrease';
    const AUDIT_ACTION_ADJUST = 'adjust';
    const AUDIT_ACTION_IMPORT = 'import';
    const AUDIT_ACTION_UPDATE_SKU = 'update_sku';
    const AUDIT_ACTION_UPDATE_STYLE = 'update_style';
    const AUDIT_ACTION_ACTIVATE = 'activate';
    const AUDIT_ACTION_DEACTIVATE = 'deactivate';
    const AUDIT_ACTION_BULK_UPDATE = 'bulk_update';
    
    // Production status for reserved calculation
    const PRODUCTION_STATUS_PENDING = 'pending';
}
