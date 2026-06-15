<?php

namespace App\Constants;

class SupportConstants
{
    // Pagination
    const DEFAULT_PER_PAGE = 20;
    const DEFAULT_PAGE = 1;
    const DEFAULT_SORT_BY = 'updated_at';
    const DEFAULT_SORT_ORDER = 'desc';
    
    // Status
    const STATUS_NEW = 0;
    const STATUS_SOLVED = 1;
    
    // Response messages
    const TICKETS_RETRIEVED = 'Tickets retrieved successfully';
    const TICKET_RETRIEVED = 'Ticket retrieved successfully';
    const TICKET_CREATED = 'Ticket created successfully';
    const TICKET_UPDATED = 'Ticket updated successfully';
    const TICKETS_RETRIEVAL_FAILED = 'Failed to retrieve tickets';
    const TICKET_CREATION_FAILED = 'Failed to create ticket';
    const TICKET_UPDATE_FAILED = 'Failed to update ticket';
    const TICKET_NOT_FOUND = 'Ticket not found';
    
    const MESSAGE_SENT = 'Message sent successfully';
    const MESSAGE_SEND_FAILED = 'Failed to send message';
    
    const UNAUTHORIZED = 'Unauthorized';
    const INVALID_STATUS = 'Invalid ticket status';
    
    // Sortable fields
    const SORTABLE_FIELDS = ['id', 'order_id', 'status', 'created_at', 'updated_at'];
}
