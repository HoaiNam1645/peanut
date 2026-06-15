<?php

namespace App\Services;

use App\Constants\HttpCode;
use App\Constants\SupportConstants;
use App\Enums\TimelineObject;
use App\Enums\UserRole;
use App\Events\TicketCreated;
use App\Events\TicketMessageSent;
use App\Events\TicketUpdated;
use App\Models\Support;
use App\Models\SupportChat;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SupportService
{
    /**
     * Get tickets list with pagination and filters
     */
    public function getTicketsList(array $params, User $currentUser): array
    {
        try {
            $userRole = $currentUser->role->name ?? null;

            // Pagination
            $perPage = $params['per_page'] ?? SupportConstants::DEFAULT_PER_PAGE;
            $page = $params['page'] ?? SupportConstants::DEFAULT_PAGE;

            // Build query with eager loading
            $query = Support::query()
                ->with([
                    'order:id,order_stt,ref_id,seller_id',
                    'order.seller:id,username,email',
                    'user:id,username,email',
                    'chats' => function ($chatQuery) {
                        $chatQuery->with('user:id,username,email,role_id')
                            ->latest()
                            ->limit(1);
                    }
                ])
                ->select([
                    'id',
                    'user_id',
                    'order_id',
                    'subject',
                    'image_link',
                    'status',
                    'user_reply',
                    'user_solved',
                    'created_at',
                    'updated_at'
                ]);

            // Role-based filtering
            if ($userRole === UserRole::all()[UserRole::SELLER]) {
                // Seller only sees tickets for their orders
                $query->whereHas('order', function ($orderQuery) use ($currentUser) {
                    $orderQuery->where('seller_id', $currentUser->id);
                });
            }
            // Admin/Support sees all tickets

            // Status filter (quick tabs)
            if (isset($params['status']) && $params['status'] !== '') {
                $query->where('status', (int) $params['status']);
            }

            // Ticket ID filter
            if (!empty($params['ticket_id'])) {
                $query->where('id', $params['ticket_id']);
            }

            // Order ID filter
            if (!empty($params['order_id'])) {
                $query->whereHas('order', function ($orderQuery) use ($params) {
                    $orderQuery->where('order_stt', 'like', "%{$params['order_id']}%")
                        ->orWhere('ref_id', 'like', "%{$params['order_id']}%");
                });
            }

            // Subject filter
            if (!empty($params['subject'])) {
                $query->where('subject', 'like', "%{$params['subject']}%");
            }

            // Seller filter (Admin/Support only)
            if (
                in_array($userRole, [UserRole::all()[UserRole::ADMIN], UserRole::all()[UserRole::SUPPORT]])
                && !empty($params['seller_id'])
            ) {
                $query->whereHas('order', function ($orderQuery) use ($params) {
                    $orderQuery->where('seller_id', $params['seller_id']);
                });
            }

            // Support filter (Admin only) - filter by user_reply role
            if ($userRole === UserRole::all()[UserRole::ADMIN] && !empty($params['support_id'])) {
                $query->where('user_reply', $params['support_id']);
            }

            // Sorting
            $sortBy = $params['sort_by'] ?? SupportConstants::DEFAULT_SORT_BY;
            $sortOrder = $params['sort_order'] ?? SupportConstants::DEFAULT_SORT_ORDER;

            if (in_array($sortBy, SupportConstants::SORTABLE_FIELDS)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy(SupportConstants::DEFAULT_SORT_BY, SupportConstants::DEFAULT_SORT_ORDER);
            }

            // Paginate
            $tickets = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data
            $transformedTickets = $tickets->getCollection()->map(function ($ticket) use ($userRole) {
                return $this->transformTicketData($ticket, $userRole);
            });

            return [
                'success' => true,
                'data' => [
                    'tickets' => $transformedTickets,
                    'pagination' => [
                        'current_page' => $tickets->currentPage(),
                        'per_page' => $tickets->perPage(),
                        'total' => $tickets->total(),
                        'last_page' => $tickets->lastPage(),
                        'from' => $tickets->firstItem(),
                        'to' => $tickets->lastItem(),
                    ]
                ]
            ];
        } catch (Exception $e) {
            Log::error('Failed to get tickets list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get ticket by ID with messages
     */
    public function getTicketById(int $ticketId, User $currentUser): array
    {
        try {
            $userRole = $currentUser->role->name ?? null;

            $query = Support::query()
                ->with([
                    'order:id,order_stt,ref_id,seller_id',
                    'order.seller:id,username,email',
                    'user:id,username,email',
                    'chats' => function ($chatQuery) {
                        $chatQuery->with('user:id,username,email,role_id')
                            ->orderBy('created_at', 'asc');
                    }
                ]);

            // Role-based access control
            if ($userRole === UserRole::all()[UserRole::SELLER]) {
                $query->whereHas('order', function ($orderQuery) use ($currentUser) {
                    $orderQuery->where('seller_id', $currentUser->id);
                });
            }

            $ticket = $query->find($ticketId);

            if (!$ticket) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => SupportConstants::TICKET_NOT_FOUND
                ];
            }

            return [
                'success' => true,
                'data' => $this->transformTicketDetailData($ticket)
            ];
        } catch (Exception $e) {
            Log::error('Failed to get ticket', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create new ticket
     */
    public function createTicket(array $data, User $currentUser, $file = null): array
    {
        try {
            DB::beginTransaction();

            $imageLink = null;

            // Upload image to B2 if provided
            if ($file) {
                try {
                    $imageLink = $this->uploadImageToB2($file, $currentUser->id);
                    Log::info('Image uploaded to B2 successfully', [
                        'user_id' => $currentUser->id,
                        'image_url' => $imageLink
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to upload image to B2', [
                        'error' => $e->getMessage(),
                        'user_id' => $currentUser->id
                    ]);
                    // Continue without image if upload fails
                }
            }

            $ticket = Support::create([
                'user_id' => $currentUser->id,
                'order_id' => $data['order_id'],
                'subject' => $data['subject'],
                'image_link' => $imageLink,
                'status' => SupportConstants::STATUS_NEW,
                'user_reply' => $currentUser->role_id,
            ]);

            // Create first message
            if (!empty($data['message'])) {
                SupportChat::create([
                    'support_id' => $ticket->id,
                    'user_id' => $currentUser->id,
                    'message' => $data['message'],
                ]);
            }

            // Create timeline entry
            Timeline::create([
                'object' => TimelineObject::TICKET,
                'object_id' => $ticket->id,
                'owner_id' => $currentUser->id,
                'action' => 'created',
                'note' => "Ticket created: {$ticket->subject}",
            ]);

            DB::commit();

            Log::info('Ticket created successfully', [
                'ticket_id' => $ticket->id,
                'created_by' => $currentUser->id,
                'has_image' => !empty($imageLink)
            ]);

            // Broadcast ticket created event for real-time updates
            try {
                broadcast(new TicketCreated($ticket))->toOthers();
            } catch (Exception $e) {
                Log::warning('Failed to broadcast ticket created', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'success' => true,
                'data' => $ticket->load(['order', 'user', 'chats'])
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create ticket', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload image to Backblaze B2
     */
    protected function uploadImageToB2($file, int $userId): string
    {
        // Generate unique filename
        $timestamp = time();
        $extension = $file->getClientOriginalExtension();
        $filename = "/support/ticket_{$userId}_{$timestamp}.{$extension}";

        // Get file content
        $fileContent = file_get_contents($file->getRealPath());

        if (empty($fileContent)) {
            throw new Exception("Failed to read file content");
        }

        // Upload to B2
        \Illuminate\Support\Facades\Storage::disk('b2')->put($filename, $fileContent, 'public');

        // Return full URL
        return env('B2_URL', 'https://s3.us-east-005.backblazeb2.com') . $filename;
    }

    /**
     * Update ticket status
     */
    public function updateTicketStatus(int $ticketId, int $status, User $currentUser): array
    {
        try {
            $ticket = Support::find($ticketId);

            if (!$ticket) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => SupportConstants::TICKET_NOT_FOUND
                ];
            }

            // Validate status
            if (!in_array($status, [SupportConstants::STATUS_NEW, SupportConstants::STATUS_SOLVED])) {
                return [
                    'success' => false,
                    'code' => HttpCode::BAD_REQUEST,
                    'message' => SupportConstants::INVALID_STATUS
                ];
            }

            $oldStatus = $ticket->status;
            $ticket->status = $status;

            if ($status === SupportConstants::STATUS_SOLVED) {
                $ticket->user_solved = $currentUser->id;
            }

            $ticket->save();

            // Create timeline entry
            $statusText = $status === SupportConstants::STATUS_SOLVED ? 'Solved' : 'New';
            $oldStatusText = $oldStatus === SupportConstants::STATUS_SOLVED ? 'Solved' : 'New';

            Timeline::create([
                'object' => TimelineObject::TICKET,
                'object_id' => $ticketId,
                'owner_id' => $currentUser->id,
                'action' => 'status_changed',
                'note' => "Status changed from {$oldStatusText} to {$statusText}",
            ]);

            Log::info('Ticket status updated', [
                'ticket_id' => $ticketId,
                'status' => $status,
                'updated_by' => $currentUser->id
            ]);

            // Broadcast ticket updated event for real-time updates
            try {
                broadcast(new TicketUpdated($ticket))->toOthers();
            } catch (Exception $e) {
                Log::warning('Failed to broadcast ticket updated', [
                    'ticket_id' => $ticketId,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'success' => true,
                'data' => $ticket->load(['order', 'user', 'chats'])
            ];
        } catch (Exception $e) {
            Log::error('Failed to update ticket status', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send message to ticket
     */
    public function sendMessage(int $ticketId, array $data, User $currentUser, $file = null): array
    {
        try {
            DB::beginTransaction();

            $ticket = Support::find($ticketId);

            if (!$ticket) {
                return [
                    'success' => false,
                    'code' => HttpCode::NOT_FOUND,
                    'message' => SupportConstants::TICKET_NOT_FOUND
                ];
            }

            $imageLink = null;

            // Upload image to B2 if provided
            if ($file) {
                try {
                    $imageLink = $this->uploadImageToB2($file, $currentUser->id);
                    Log::info('Image uploaded to B2 for message', [
                        'user_id' => $currentUser->id,
                        'ticket_id' => $ticketId,
                        'image_url' => $imageLink
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to upload image to B2 for message', [
                        'error' => $e->getMessage(),
                        'user_id' => $currentUser->id,
                        'ticket_id' => $ticketId
                    ]);

                    DB::rollBack();
                    return [
                        'success' => false,
                        'error' => 'Failed to upload image: ' . $e->getMessage()
                    ];
                }
            }

            // Create message
            // If image is uploaded, save image URL in message field
            // Otherwise, save the text message
            $messageContent = $imageLink ? $imageLink : ($data['message'] ?? '');

            // Validate: Must have content
            if (empty($messageContent)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'code' => HttpCode::BAD_REQUEST,
                    'message' => 'Message content is required'
                ];
            }

            $message = SupportChat::create([
                'support_id' => $ticketId,
                'user_id' => $currentUser->id,
                'message' => $messageContent,
            ]);

            // Check if ticket is solved and seller is replying -> reopen ticket
            $currentUser->load('role');
            $userRole = $currentUser->role->name ?? null;
            $wasSolved = $ticket->status === SupportConstants::STATUS_SOLVED;

            if ($wasSolved && $userRole === 'Seller') {
                $ticket->status = SupportConstants::STATUS_NEW;

                // Create timeline entry for auto-reopen
                Timeline::create([
                    'object' => TimelineObject::TICKET,
                    'object_id' => $ticketId,
                    'owner_id' => $currentUser->id,
                    'action' => 'status_changed',
                    'note' => 'Status changed from Solved to New (auto-reopened by seller reply)',
                ]);

                Log::info('Ticket auto-reopened by seller reply', [
                    'ticket_id' => $ticketId,
                    'seller_id' => $currentUser->id
                ]);
            }

            // Update ticket user_reply to current user's role
            $ticket->user_reply = $currentUser->role_id;
            $ticket->touch(); // Update updated_at
            $ticket->save();

            // NOTE: Timeline không lưu mỗi message chat để tránh quá nhiều records
            // Timeline chỉ lưu các sự kiện quan trọng:
            // - Ticket created (trong createTicket)
            // - Status changed (trong updateTicketStatus)

            DB::commit();

            // Broadcast the message to WebSocket (individual ticket channel)
            try {
                broadcast(new TicketMessageSent($message, $ticketId))->toOthers();
            } catch (Exception $e) {
                Log::warning('Failed to broadcast message', [
                    'ticket_id' => $ticketId,
                    'message_id' => $message->id,
                    'error' => $e->getMessage()
                ]);
                // Continue even if broadcast fails
            }

            // Broadcast ticket updated for list updates (status change, new message time)
            try {
                broadcast(new TicketUpdated($ticket))->toOthers();
            } catch (Exception $e) {
                Log::warning('Failed to broadcast ticket updated', [
                    'ticket_id' => $ticketId,
                    'error' => $e->getMessage()
                ]);
            }

            Log::info('Message sent to ticket', [
                'ticket_id' => $ticketId,
                'message_id' => $message->id,
                'user_id' => $currentUser->id,
                'has_image' => !empty($imageLink)
            ]);

            return [
                'success' => true,
                'data' => $message->load('user')
            ];
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to send message', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Transform ticket data for response
     */
    protected function transformTicketData($ticket, $userRole): array
    {
        $lastChat = $ticket->chats->first();

        $data = [
            'id' => $ticket->id,
            'order' => $ticket->order ? [
                'id' => $ticket->order->id,
                'order_stt' => $ticket->order->order_stt,
                'ref_id' => $ticket->order->ref_id,
            ] : null,
            'subject' => $ticket->subject,
            'image_link' => $ticket->image_link,
            'status' => $ticket->status,
            'status_text' => $ticket->status === SupportConstants::STATUS_SOLVED ? 'Solved' : 'New',
            'user_reply' => $lastChat ? [
                'id' => $lastChat->user->id ?? null,
                'username' => $lastChat->user->username ?? 'N/A',
                'role_id' => $lastChat->user->role_id ?? null,
            ] : null,
            'last_reply' => $lastChat ? $lastChat->message : null,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];

        // Only show owner (seller) for Admin/Support
        if (in_array($userRole, [UserRole::all()[UserRole::ADMIN], UserRole::all()[UserRole::SUPPORT]])) {
            $data['owner'] = $ticket->order && $ticket->order->seller ? [
                'id' => $ticket->order->seller->id,
                'username' => $ticket->order->seller->username,
                'email' => $ticket->order->seller->email,
            ] : null;
        }

        return $data;
    }

    /**
     * Transform ticket detail data with messages
     */
    protected function transformTicketDetailData($ticket): array
    {
        return [
            'id' => $ticket->id,
            'order' => $ticket->order ? [
                'id' => $ticket->order->id,
                'order_stt' => $ticket->order->order_stt,
                'ref_id' => $ticket->order->ref_id,
                'seller' => $ticket->order->seller ? [
                    'id' => $ticket->order->seller->id,
                    'username' => $ticket->order->seller->username,
                    'email' => $ticket->order->seller->email,
                ] : null,
            ] : null,
            'user' => [
                'id' => $ticket->user->id ?? null,
                'username' => $ticket->user->username ?? 'N/A',
                'email' => $ticket->user->email ?? 'N/A',
            ],
            'subject' => $ticket->subject,
            'image_link' => $ticket->image_link,
            'status' => $ticket->status,
            'status_text' => $ticket->status === SupportConstants::STATUS_SOLVED ? 'Solved' : 'New',
            'messages' => $ticket->chats->map(function ($chat) {
                return [
                    'id' => $chat->id,
                    'user' => [
                        'id' => $chat->user->id,
                        'username' => $chat->user->username,
                        'email' => $chat->user->email,
                        'role_id' => $chat->user->role_id,
                    ],
                    'message' => $chat->message,
                    'image_link' => $chat->image_link,
                    'created_at' => $chat->created_at,
                ];
            }),
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];
    }
}
