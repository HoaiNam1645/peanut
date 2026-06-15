<?php

namespace App\Swagger\Seller\Docs;

/**
 * @OA\Get(
 *     path="/api/transactions",
 *     operationId="sellerListTransactions",
 *     tags={"Wallet"},
 *     summary="Lịch sử giao dịch ví",
 *     description="Seller chỉ thấy giao dịch của mình. Yêu cầu transactions.view.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"deposit", "withdrawal", "payment", "refund"})),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending", "approved", "rejected", "completed"})),
 *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/transactions/add-fund",
 *     operationId="sellerAddFund",
 *     tags={"Wallet"},
 *     summary="Yêu cầu nạp tiền vào ví",
 *     description="Gửi yêu cầu nạp tiền — admin phải duyệt mới được cộng vào ví. Yêu cầu transactions.add_fund.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"transaction_id", "amount", "type"},
 *             @OA\Property(property="transaction_id", type="string", example="TXN-20260511-001", description="Mã giao dịch duy nhất do client tạo"),
 *             @OA\Property(property="amount", type="number", format="float", example=500000),
 *             @OA\Property(property="type", type="string", enum={"deposit", "withdrawal"}, example="deposit"),
 *             @OA\Property(property="payment_method", type="string", example="bank_transfer"),
 *             @OA\Property(property="proof_url", type="string", description="URL biên lai chuyển khoản"),
 *             @OA\Property(property="note", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Đã gửi yêu cầu, chờ admin duyệt"),
 *     @OA\Response(response=400, description="Validation lỗi hoặc trùng transaction_id")
 * )
 *
 * @OA\Get(
 *     path="/api/transactions/export",
 *     operationId="sellerExportTransactions",
 *     tags={"Wallet"},
 *     summary="Export lịch sử giao dịch (CSV)",
 *     description="Yêu cầu transactions.export.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="File CSV",
 *         @OA\MediaType(mediaType="text/csv")
 *     )
 * )
 */
class WalletDocs
{
    // Class container for Seller Wallet API documentation
}
