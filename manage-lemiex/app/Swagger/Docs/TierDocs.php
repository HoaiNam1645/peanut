<?php

namespace App\Swagger\Docs;

/**
 * ==================== TIER ENDPOINTS ====================
 *
 * @OA\Get(
 *     path="/api/tiers",
 *     tags={"Tiers"},
 *     summary="Lấy danh sách tiers",
 *     description="Lấy tất cả tiers với extra fees, refund fees và embroidery fees. Chỉ admin mới có quyền truy cập.",
 *     security={{"bearerAuth": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(ref="#/components/schemas/TierListResponse")
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực"),
 *     @OA\Response(response=403, description="Không có quyền")
 * )
 *
 * @OA\Get(
 *     path="/api/tiers/options",
 *     tags={"Tiers"},
 *     summary="Danh sách tier options cho dropdown",
 *     description="Lấy danh sách các tier đã tạo để sử dụng trong dropdown select. Trả về ID và tên tier.",
 *     security={{"bearerAuth": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="array", @OA\Items(type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="tier_id", type="integer", example=0),
 *                 @OA\Property(property="name", type="string", example="Silver")
 *             ))
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 *
 * @OA\Post(
 *     path="/api/tiers",
 *     tags={"Tiers"},
 *     summary="Tạo tier mới",
 *     description="Tạo tier mới với mã tier cụ thể (0-3). Mỗi tier_id chỉ được tạo một lần.",
 *     security={{"bearerAuth": {}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/CreateTierRequest")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Tạo thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Tier created successfully"),
 *             @OA\Property(property="data", ref="#/components/schemas/Tier")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Tier đã tồn tại hoặc dữ liệu không hợp lệ"),
 *     @OA\Response(response=401, description="Chưa xác thực")
 * )
 *
 * @OA\Put(
 *     path="/api/tiers/{id}",
 *     tags={"Tiers"},
 *     summary="Cập nhật tier",
 *     description="Cập nhật tên tier",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID của tier",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/UpdateTierRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", ref="#/components/schemas/Tier")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Tier không tồn tại")
 * )
 *
 * @OA\Delete(
 *     path="/api/tiers/{id}",
 *     tags={"Tiers"},
 *     summary="Xóa tier",
 *     description="Xóa tier và tất cả fees liên quan",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID của tier",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Xóa thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Tier deleted successfully")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Tier không tồn tại")
 * )
 *
 * ==================== EXTRA FEE ENDPOINTS ====================
 *
 * @OA\Post(
 *     path="/api/tiers/{tierId}/extra-fee",
 *     tags={"Tiers - Extra Fee"},
 *     summary="Thêm extra fee cho tier",
 *     description="Thêm phụ phí theo khoảng stitch cho tier. Phụ phí được áp dụng khi stitch count nằm trong khoảng [min_stitch, max_stitch].",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(
 *         name="tierId",
 *         in="path",
 *         required=true,
 *         description="tier_id (0=Silver, 1=Gold, 2=Platinum, 3=Diamond)",
 *         @OA\Schema(type="integer", enum={0, 1, 2, 3})
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/AddExtraFeeRequest")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Thêm thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", ref="#/components/schemas/ExtraFee")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Khoảng stitch bị trùng lặp"),
 *     @OA\Response(response=404, description="Tier không tồn tại")
 * )
 *
 * @OA\Put(
 *     path="/api/tiers/{tierId}/extra-fee/{id}",
 *     tags={"Tiers - Extra Fee"},
 *     summary="Cập nhật extra fee",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, description="ID của extra fee", @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddExtraFeeRequest")),
 *     @OA\Response(response=200, description="Cập nhật thành công"),
 *     @OA\Response(response=404, description="Không tìm thấy")
 * )
 *
 * @OA\Delete(
 *     path="/api/tiers/{tierId}/extra-fee/{id}",
 *     tags={"Tiers - Extra Fee"},
 *     summary="Xóa extra fee",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xóa thành công"),
 *     @OA\Response(response=404, description="Không tìm thấy")
 * )
 *
 * ==================== REFUND FEE ENDPOINTS ====================
 *
 * @OA\Post(
 *     path="/api/tiers/{tierId}/refund-fee",
 *     tags={"Tiers - Refund Fee"},
 *     summary="Thêm refund fee cho tier",
 *     description="Thêm phí hoàn trả khi đơn hàng chỉ có 1 mặt (front only). Hoàn trả dựa trên stitch count.",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer", enum={0, 1, 2, 3})),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddRefundFeeRequest")),
 *     @OA\Response(response=201, description="Thêm thành công"),
 *     @OA\Response(response=404, description="Tier không tồn tại")
 * )
 *
 * @OA\Put(
 *     path="/api/tiers/{tierId}/refund-fee/{id}",
 *     tags={"Tiers - Refund Fee"},
 *     summary="Cập nhật refund fee",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddRefundFeeRequest")),
 *     @OA\Response(response=200, description="Cập nhật thành công"),
 *     @OA\Response(response=404, description="Không tìm thấy")
 * )
 *
 * @OA\Delete(
 *     path="/api/tiers/{tierId}/refund-fee/{id}",
 *     tags={"Tiers - Refund Fee"},
 *     summary="Xóa refund fee",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xóa thành công"),
 *     @OA\Response(response=404, description="Không tìm thấy")
 * )
 *
 * ==================== EMBROIDERY FEE ENDPOINTS ====================
 *
 * @OA\Post(
 *     path="/api/tiers/{tierId}/embroidery-fee",
 *     tags={"Tiers - Embroidery Fee"},
 *     summary="Thêm embroidery fee cho tier",
 *     description="Thêm phí thêu đặc biệt cho tier. Áp dụng cho các loại thêu: metallic, glow, puff. Loại 'standard' không tính phí.",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer", enum={0, 1, 2, 3})),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddEmbroideryFeeRequest")),
 *     @OA\Response(
 *         response=201,
 *         description="Thêm thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", ref="#/components/schemas/EmbroideryFee")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Khoảng stitch bị trùng lặp cho cùng loại thêu"),
 *     @OA\Response(response=404, description="Tier không tồn tại")
 * )
 *
 * @OA\Put(
 *     path="/api/tiers/{tierId}/embroidery-fee/{id}",
 *     tags={"Tiers - Embroidery Fee"},
 *     summary="Cập nhật embroidery fee",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddEmbroideryFeeRequest")),
 *     @OA\Response(response=200, description="Cập nhật thành công"),
 *     @OA\Response(response=404, description="Không tìm thấy")
 * )
 *
 * @OA\Delete(
 *     path="/api/tiers/{tierId}/embroidery-fee/{id}",
 *     tags={"Tiers - Embroidery Fee"},
 *     summary="Xóa embroidery fee",
 *     security={{"bearerAuth": {}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xóa thành công"),
 *     @OA\Response(response=404, description="Không tìm thấy")
 * )
 */
class TierDocs
{
    // Chứa documentation cho Tier endpoints
}
