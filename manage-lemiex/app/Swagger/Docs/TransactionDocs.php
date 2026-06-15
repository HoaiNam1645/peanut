<?php

namespace App\Swagger\Docs;

/**
 * ==================== TRANSACTION API DOCUMENTATION ====================
 *
 * @OA\Get(
 *     path="/api/transactions",
 *     operationId="getTransactions",
 *     tags={"Transactions"},
 *     summary="Danh sách giao dịch",
 *     description="Lấy danh sách giao dịch với phân trang và bộ lọc. Admin thấy tất cả, Seller chỉ thấy giao dịch của mình.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang", @OA\Schema(type="integer", default=10)),
 *     @OA\Parameter(name="page", in="query", description="Số trang", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="seller_id", in="query", description="Lọc theo seller (chỉ Admin)", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="date_from", in="query", description="Từ ngày", @OA\Schema(type="string", format="date", example="2024-01-01")),
 *     @OA\Parameter(name="date_to", in="query", description="Đến ngày", @OA\Schema(type="string", format="date", example="2024-12-31")),
 *     @OA\Parameter(name="type", in="query", description="Loại giao dịch", @OA\Schema(type="string", enum={"Payment", "Refund", "Deposit"})),
 *     @OA\Parameter(name="status", in="query", description="Trạng thái", @OA\Schema(type="string", enum={"pending", "completed", "failed"})),
 *     @OA\Parameter(name="search", in="query", description="Tìm theo transaction ID hoặc ghi chú", @OA\Schema(type="string")),
 *     @OA\Parameter(name="sort_by", in="query", description="Sắp xếp theo", @OA\Schema(type="string", default="created_at")),
 *     @OA\Parameter(name="sort_order", in="query", description="Thứ tự", @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Transactions retrieved successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="items", type="array", @OA\Items(type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="transaction_id", type="string", example="TXN-123456"),
 *                     @OA\Property(property="type", type="string", example="Payment"),
 *                     @OA\Property(property="amount", type="number", format="float", example=100.50),
 *                     @OA\Property(property="status", type="string", example="completed"),
 *                     @OA\Property(property="note", type="string", example="Payment for order #123"),
 *                     @OA\Property(property="created_at", type="string", format="date-time")
 *                 )),
 *                 @OA\Property(property="summary", type="object",
 *                     @OA\Property(property="total_deposit", type="number", example=5000.00),
 *                     @OA\Property(property="total_payment", type="number", example=3000.00),
 *                     @OA\Property(property="total_refund", type="number", example=200.00),
 *                     @OA\Property(property="balance", type="number", example=1800.00)
 *                 ),
 *                 @OA\Property(property="pagination", type="object")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/transactions/add-fund",
 *     operationId="addFund",
 *     tags={"Transactions"},
 *     summary="Nạp tiền vào ví",
 *     description="Thêm giao dịch nạp tiền vào ví của seller. Yêu cầu quyền Admin.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"type", "amount", "transaction_id"},
 *             @OA\Property(property="type", type="string", enum={"Payment", "Refund", "Deposit"}, example="Deposit"),
 *             @OA\Property(property="amount", type="number", format="float", example=1000.00, description="Số tiền (phải > 0)"),
 *             @OA\Property(property="transaction_id", type="string", example="TXN-UNIQUE-123", description="Mã giao dịch duy nhất"),
 *             @OA\Property(property="note", type="string", example="Nạp tiền qua PayPal", description="Ghi chú (tối đa 500 ký tự)")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Nạp tiền thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Transaction created successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="transaction_id", type="string"),
 *                 @OA\Property(property="amount", type="number"),
 *                 @OA\Property(property="new_balance", type="number", example=2000.00)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi hoặc transaction_id đã tồn tại"),
 *     @OA\Response(response=401, description="Chưa xác thực"),
 *     @OA\Response(response=403, description="Không có quyền")
 * )
 *
 * @OA\Get(
 *     path="/api/transactions/export",
 *     operationId="exportTransactions",
 *     tags={"Transactions"},
 *     summary="Xuất giao dịch ra CSV",
 *     description="Xuất danh sách giao dịch ra file CSV với các bộ lọc",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="seller_id", in="query", description="Lọc theo seller", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"Payment", "Refund", "Deposit"})),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending", "completed", "failed"})),
 *     @OA\Response(
 *         response=200,
 *         description="Export thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Export completed successfully"),
 *             @OA\Property(property="data", type="object",
 *                 @OA\Property(property="csv", type="string", description="Nội dung CSV"),
 *                 @OA\Property(property="filename", type="string", example="transactions_export_2024-01-01.csv")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 *
 * @OA\Get(
 *     path="/api/transactions/sellers",
 *     operationId="getTransactionSellers",
 *     tags={"Transactions"},
 *     summary="Danh sách sellers cho dropdown",
 *     description="Lấy danh sách tất cả sellers để hiển thị trong dropdown lọc",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Sellers retrieved successfully"),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="username", type="string", example="seller01"),
 *                 @OA\Property(property="email", type="string", example="seller@example.com")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 */
class TransactionDocs
{
    // Transaction API Documentation
}
