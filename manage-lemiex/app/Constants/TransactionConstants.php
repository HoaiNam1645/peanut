<?php

namespace App\Constants;

class TransactionConstants
{
    // Pagination
    const DEFAULT_PER_PAGE = 50;
    const DEFAULT_PAGE = 1;
    const DEFAULT_SORT_BY = 'created_at';
    const DEFAULT_SORT_ORDER = 'desc';
    
    // Response messages
    const TRANSACTIONS_RETRIEVED = 'Transactions retrieved successfully';
    const TRANSACTION_CREATED = 'Transaction created successfully';
    const TRANSACTIONS_RETRIEVAL_FAILED = 'Failed to retrieve transactions';
    const TRANSACTION_CREATION_FAILED = 'Failed to create transaction';
    const TRANSACTION_EXPORT_FAILED = 'Failed to export transactions';
    
    const UNAUTHORIZED = 'Unauthorized';
    const INSUFFICIENT_BALANCE = 'Insufficient balance';
    const INVALID_AMOUNT = 'Invalid amount';
    
    // Sortable fields
    const SORTABLE_FIELDS = ['id', 'order_id', 'amount', 'fee', 'remaining_balance', 'status', 'created_at'];
    
    // Export
    const EXPORT_FILENAME_PREFIX = 'transactions_';
    const EXPORT_DATE_FORMAT = 'Y-m-d_His';
}
