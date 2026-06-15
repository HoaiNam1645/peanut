<?php

namespace App\Swagger\Seller\Docs;

/**
 * @OA\Get(
 *     path="/api/tickets",
 *     operationId="sellerListTickets",
 *     tags={"Tickets"},
 *     summary="Danh sách ticket khiếu nại của seller",
 *     description="Yêu cầu tickets.view.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"open", "in_progress", "resolved", "closed"})),
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Search theo subject, order ref_id"),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/tickets/{id}",
 *     operationId="sellerGetTicketDetail",
 *     tags={"Tickets"},
 *     summary="Chi tiết ticket kèm danh sách messages",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công"),
 *     @OA\Response(response=404, description="Ticket không tồn tại")
 * )
 *
 * @OA\Post(
 *     path="/api/tickets",
 *     operationId="sellerCreateTicket",
 *     tags={"Tickets"},
 *     summary="Tạo ticket mới",
 *     description="Yêu cầu tickets.create.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"subject", "message"},
 *             @OA\Property(property="subject", type="string", example="Đơn hàng bị giao chậm"),
 *             @OA\Property(property="message", type="string", example="Đơn ORDER-123 đã 2 tuần chưa giao..."),
 *             @OA\Property(property="order_id", type="integer", description="Liên kết với đơn hàng cụ thể (tuỳ chọn)"),
 *             @OA\Property(property="priority", type="string", enum={"low", "normal", "high", "urgent"}, default="normal"),
 *             @OA\Property(property="category", type="string", example="shipping", description="shipping, quality, refund, other"),
 *             @OA\Property(property="attachments", type="array", @OA\Items(type="string"), description="URLs file đính kèm")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tạo ticket thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/tickets/{id}/messages",
 *     operationId="sellerSendTicketMessage",
 *     tags={"Tickets"},
 *     summary="Gửi tin nhắn vào ticket",
 *     description="Reply lên ticket. Yêu cầu tickets.send_message.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"message"},
 *             @OA\Property(property="message", type="string", example="Cảm ơn support, đã nhận được hàng"),
 *             @OA\Property(property="attachments", type="array", @OA\Items(type="string"))
 *         )
 *     ),
 *     @OA\Response(response=200, description="Gửi tin thành công")
 * )
 */
class TicketDocs
{
    // Class container for Seller Ticket API documentation
}
