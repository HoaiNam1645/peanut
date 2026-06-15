<?php

namespace App\Swagger\Docs;

/**
 * ==================== SUPPORT TICKET API DOCUMENTATION ====================
 *
 * @OA\Get(
 *     path="/api/tickets",
 *     operationId="getTickets",
 *     tags={"Support Tickets"},
 *     summary="Danh sách ticket hỗ trợ",
 *     description="Lấy danh sách tickets với phân trang và bộ lọc. Admin/Support thấy tất cả, Seller chỉ thấy tickets của mình.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang", @OA\Schema(type="integer", default=10)),
 *     @OA\Parameter(name="page", in="query", description="Số trang", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string", enum={"open", "in_progress", "resolved", "closed"})),
 *     @OA\Parameter(name="ticket_id", in="query", description="Tìm theo ticket ID", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="order_id", in="query", description="Tìm theo order ID liên quan", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="subject", in="query", description="Tìm theo tiêu đề ticket", @OA\Schema(type="string")),
 *     @OA\Parameter(name="seller_id", in="query", description="Lọc theo seller (Admin/Support)", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="support_id", in="query", description="Lọc theo người xử lý", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", default="created_at")),
 *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Tickets retrieved successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="items", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="title", type="string", example="Vấn đề với đơn hàng #123"),
 *                     @OA\Property(property="status", type="string", example="open"),
 *                     @OA\Property(property="priority", type="string", example="high"),
 *                     @OA\Property(property="order_id", type="integer", example=123),
 *                     @OA\Property(property="seller", type="object"),
 *                     @OA\Property(property="assignee", type="object"),
 *                     @OA\Property(property="last_message_at", type="string", format="date-time"),
 *                     @OA\Property(property="created_at", type="string", format="date-time")
 *                 )),
 *                 @OA\Property(property="pagination", type="object")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 *
 * @OA\Post(
 *     path="/api/tickets",
 *     operationId="createTicket",
 *     tags={"Support Tickets"},
 *     summary="Tạo ticket mới",
 *     description="Tạo ticket hỗ trợ mới. Seller có thể tạo ticket liên quan đến đơn hàng của mình.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"order_id", "subject", "message"},
 *                 @OA\Property(property="order_id", type="integer", example=123, description="ID đơn hàng liên quan (bắt buộc)"),
 *                 @OA\Property(property="subject", type="string", example="Vấn đề với chất lượng sản phẩm", maxLength=255),
 *                 @OA\Property(property="message", type="string", example="Sản phẩm bị lỗi in, màu không đúng..."),
 *                 @OA\Property(property="file", type="string", format="binary", description="File đính kèm (jpg,jpeg,png,gif,pdf - max 10MB)")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Tạo thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=201),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Ticket created successfully"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi"),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 *
 * @OA\Get(
 *     path="/api/tickets/{id}",
 *     operationId="getTicketById",
 *     tags={"Support Tickets"},
 *     summary="Chi tiết ticket",
 *     description="Lấy thông tin chi tiết ticket bao gồm tất cả tin nhắn",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID ticket", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="subject", type="string"),
 *                 @OA\Property(property="status", type="string"),
 *                 @OA\Property(property="order", type="object"),
 *                 @OA\Property(property="seller", type="object"),
 *                 @OA\Property(property="assignee", type="object"),
 *                 @OA\Property(property="messages", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer"),
 *                     @OA\Property(property="content", type="string"),
 *                     @OA\Property(property="sender", type="object"),
 *                     @OA\Property(property="created_at", type="string", format="date-time")
 *                 ))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy ticket"),
 *     @OA\Response(response=403, description="Không có quyền xem ticket này")
 * )
 *
 * @OA\Put(
 *     path="/api/tickets/{id}/status",
 *     operationId="updateTicketStatus",
 *     tags={"Support Tickets"},
 *     summary="Cập nhật trạng thái ticket",
 *     description="Cập nhật trạng thái của ticket. Yêu cầu quyền Admin.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"status"},
 *             @OA\Property(property="status", type="string", enum={"open", "in_progress", "resolved", "closed"}),
 *             @OA\Property(property="assignee_id", type="integer", description="ID người xử lý (optional)")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Ticket status updated successfully")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy ticket"),
 *     @OA\Response(response=403, description="Không có quyền")
 * )
 *
 * @OA\Post(
 *     path="/api/tickets/{id}/messages",
 *     operationId="sendTicketMessage",
 *     tags={"Support Tickets"},
 *     summary="Gửi tin nhắn vào ticket",
 *     description="Gửi tin nhắn mới vào ticket. Seller và Support đều có thể gửi.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"content"},
 *             @OA\Property(property="content", type="string", example="Cảm ơn bạn đã phản hồi. Tôi sẽ kiểm tra lại...")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Gửi thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Message sent successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="content", type="string"),
 *                 @OA\Property(property="created_at", type="string", format="date-time")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=404, description="Không tìm thấy ticket"),
 *     @OA\Response(response=403, description="Không có quyền gửi tin nhắn vào ticket này")
 * )
 *
 * @OA\Get(
 *     path="/api/tickets/sellers",
 *     operationId="getTicketSellers",
 *     tags={"Support Tickets"},
 *     summary="Danh sách sellers cho dropdown",
 *     description="Lấy danh sách sellers để lọc tickets",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="username", type="string"),
 *                 @OA\Property(property="email", type="string")
 *             ))
 *         )
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/tickets/supports",
 *     operationId="getTicketSupports",
 *     tags={"Support Tickets"},
 *     summary="Danh sách support users cho dropdown",
 *     description="Lấy danh sách nhân viên support để assign tickets",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer"),
 *                 @OA\Property(property="username", type="string"),
 *                 @OA\Property(property="email", type="string")
 *             ))
 *         )
 *     )
 * )
 */
class SupportDocs
{
    // Support Ticket API Documentation
}
