<?php

namespace App\Constants;

class StoreConstants
{
    // API Key format
    const API_KEY_LENGTH = 23; // 8-4-4-4 + 3 dashes = 23 characters
    const API_KEY_REGEX = '/^[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/';
    
    // Pagination
    const DEFAULT_PER_PAGE = 10;
    const DEFAULT_PAGE = 1;
    const DEFAULT_SORT_BY = 'created_at';
    const DEFAULT_SORT_ORDER = 'desc';
    
    // Validation messages
    const NAME_UNIQUE_ERROR = 'A store with this name already exists';
    const API_KEY_UNIQUE_ERROR = 'This API key already exists. Please generate a new one';
    const API_KEY_FORMAT_ERROR = 'Invalid API key format';
    
    // Response messages
    const STORE_CREATED = 'Store created successfully';
    const STORE_UPDATED = 'Store updated successfully';
    const STORE_RETRIEVED = 'Store retrieved successfully';
    const STORES_RETRIEVED = 'Stores retrieved successfully';
    const USERS_RETRIEVED = 'Users retrieved successfully';
    
    const STORE_NOT_FOUND = 'Store not found';
    const STORE_CREATION_FAILED = 'Failed to create store';
    const STORE_UPDATE_FAILED = 'Failed to update store';
    const STORES_RETRIEVAL_FAILED = 'Failed to retrieve stores';
    const USERS_RETRIEVAL_FAILED = 'Failed to retrieve users';
    
    const UNAUTHORIZED = 'Unauthorized';
    const FORBIDDEN_EDIT_OTHER_STORE = 'You can only edit your own stores';
    const FORBIDDEN_CREATE_FOR_OTHER = 'You can only create stores for yourself';
    const FORBIDDEN_CHANGE_OWNER = 'You cannot change the store owner';
    
    // Sortable fields
    const SORTABLE_FIELDS = ['id', 'name', 'created_at', 'updated_at'];
}
