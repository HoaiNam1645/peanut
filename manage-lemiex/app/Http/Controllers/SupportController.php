<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\SupportConstants;
use App\Services\SupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    protected $supportService;

    public function __construct(SupportService $supportService)
    {
        $this->supportService = $supportService;
    }

    /**
     * Get ticketsagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => SupportConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Get params
        $params = [
            'per_page' => $request->input('per_page'),
            'page' => $request->input('page'),
            'status' => $request->input('status'),
            'ticket_id' => $request->input('ticket_id'),
            'order_id' => $request->input('order_id'),
            'subject' => $request->input('subject'),
            'seller_id' => $request->input('seller_id'),
            'support_id' => $request->input('support_id'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ];

        $result = $this->supportService->getTicketsList($params, $user);

        if (!$result['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => SupportConstants::TICKETS_RETRIEVAL_FAILED,
                'error' => config('app.debug') ? $result['error'] : null
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => SupportConstants::TICKETS_RETRIEVED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Get ticket by ID with messages
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => SupportConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        $result = $this->supportService->getTicketById($id, $user);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            $message = $result['message'] ?? SupportConstants::TICKETS_RETRIEVAL_FAILED;
            
            return response()->json([
                'code' => $code,
                'status' => false,
                'message' => $message,
                'error' => config('app.debug') ? ($result['error'] ?? null) : null
            ], $code);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => SupportConstants::TICKET_RETRIEVED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Create new ticket
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => SupportConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Validate request
        try {
            $validated = $request->validate([
                'order_id' => 'required|exists:orders,id',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
                'file' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:10240', // Max 10MB
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        }

        // Get uploaded file if exists
        $file = $request->hasFile('file') ? $request->file('file') : null;

        $result = $this->supportService->createTicket($validated, $user, $file);

        if (!$result['success']) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => SupportConstants::TICKET_CREATION_FAILED,
                'error' => config('app.debug') ? $result['error'] : null
            ], HttpCode::SERVER_ERROR);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => SupportConstants::TICKET_CREATED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Update ticket status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => SupportConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Validate request
        try {
            $validated = $request->validate([
                'status' => 'required|integer|in:0,1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        }

        $result = $this->supportService->updateTicketStatus($id, $validated['status'], $user);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            $message = $result['message'] ?? SupportConstants::TICKET_UPDATE_FAILED;
            
            return response()->json([
                'code' => $code,
                'status' => false,
                'message' => $message,
                'error' => config('app.debug') ? ($result['error'] ?? null) : null
            ], $code);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => SupportConstants::TICKET_UPDATED,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Send message to ticket
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => SupportConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        // Load role relationship
        $user->load('role');

        // Validate request
        try {
            $validated = $request->validate([
                'message' => 'nullable|string', // Allow empty message when file is provided
                'file' => 'nullable|file|mimes:jpg,jpeg,png,gif,pdf|max:10240', // Max 10MB
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        }

        // Get uploaded file if exists
        $file = $request->hasFile('file') ? $request->file('file') : null;
        
        // Validate: Must have either message or file
        if (empty($validated['message']) && !$file) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Please provide either a message or a file',
            ], HttpCode::BAD_REQUEST);
        }

        $result = $this->supportService->sendMessage($id, $validated, $user, $file);

        if (!$result['success']) {
            $code = $result['code'] ?? HttpCode::SERVER_ERROR;
            $message = $result['message'] ?? SupportConstants::MESSAGE_SEND_FAILED;
            
            return response()->json([
                'code' => $code,
                'status' => false,
                'message' => $message,
                'error' => config('app.debug') ? ($result['error'] ?? null) : null
            ], $code);
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'success' => true,
            'message' => SupportConstants::MESSAGE_SENT,
            'data' => $result['data']
        ], HttpCode::SUCCESS);
    }

    /**
     * Get sellers list for filter dropdown
     */
    public function getSellers(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => SupportConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        try {
            $sellers = \App\Models\User::query()
                ->with('role:id,name')
                ->whereHas('role', function ($query) {
                    $query->where('name', 'Seller');
                })
                ->select(['id', 'username', 'email', 'role_id'])
                ->orderBy('username', 'asc')
                ->get();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'success' => true,
                'message' => 'Sellers retrieved successfully',
                'data' => $sellers
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve sellers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get support users list for filter dropdown
     */
    public function getSupports(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'code' => HttpCode::UNAUTHORIZED,
                'status' => false,
                'message' => SupportConstants::UNAUTHORIZED
            ], HttpCode::UNAUTHORIZED);
        }

        try {
            $supports = \App\Models\User::query()
                ->with('role:id,name')
                ->whereHas('role', function ($query) {
                    $query->whereIn('name', ['Admin', 'Support']);
                })
                ->select(['id', 'username', 'email', 'role_id'])
                ->orderBy('username', 'asc')
                ->get();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'success' => true,
                'message' => 'Support users retrieved successfully',
                'data' => $supports
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve support users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }
}

