<?php

namespace App\Swagger\Schemas;

/**
 * ==================== TIER SCHEMAS ====================
 * 
 * @OA\Schema(
 *     schema="Tier",
 *     type="object",
 *     description="Thông tin tier",
 *     @OA\Property(property="id", type="integer", example=1, description="ID tier"),
 *     @OA\Property(property="tier_id", type="integer", enum={0, 1, 2, 3}, example=0, description="Mã tier (0=Silver, 1=Gold, 2=Platinum, 3=Diamond)"),
 *     @OA\Property(property="name", type="string", example="Silver", description="Tên tier"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="extra_fees",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/ExtraFee"),
 *         description="Danh sách phụ phí theo stitch"
 *     ),
 *     @OA\Property(
 *         property="refund_fees",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/RefundFee"),
 *         description="Danh sách phí hoàn trả"
 *     ),
 *     @OA\Property(
 *         property="embroidery_fees",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EmbroideryFee"),
 *         description="Danh sách phí thêu đặc biệt"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ExtraFee",
 *     type="object",
 *     description="Phụ phí theo stitch count",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tier_id", type="integer", example=0),
 *     @OA\Property(property="min_stitch", type="integer", example=10000, description="Số stitch tối thiểu"),
 *     @OA\Property(property="max_stitch", type="integer", example=20000, description="Số stitch tối đa"),
 *     @OA\Property(property="amount", type="number", format="float", example=2.50, description="Số tiền phụ phí ($)")
 * )
 *
 * @OA\Schema(
 *     schema="RefundFee",
 *     type="object",
 *     description="Phí hoàn trả khi chỉ có 1 mặt",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tier_id", type="integer", example=0),
 *     @OA\Property(property="stitch", type="integer", example=5000, description="Số stitch"),
 *     @OA\Property(property="amount", type="number", format="float", example=1.00, description="Số tiền hoàn trả ($)")
 * )
 *
 * @OA\Schema(
 *     schema="EmbroideryFee",
 *     type="object",
 *     description="Phí thêu đặc biệt (metallic, glow, puff)",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tier_id", type="integer", example=0),
 *     @OA\Property(property="embroidery_type", type="string", enum={"metallic", "glow", "puff"}, example="metallic", description="Loại thêu (standard không tính phí)"),
 *     @OA\Property(property="min_stitch", type="integer", example=0, description="Số stitch tối thiểu"),
 *     @OA\Property(property="max_stitch", type="integer", example=10000, description="Số stitch tối đa"),
 *     @OA\Property(property="amount", type="number", format="float", example=1.50, description="Số tiền phí thêu ($)")
 * )
 *
 * @OA\Schema(
 *     schema="TierListResponse",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="tiers",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/Tier")
 *         ),
 *         @OA\Property(
 *             property="available_tiers",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="tier_id", type="integer", example=3),
 *                 @OA\Property(property="name", type="string", example="Diamond")
 *             ),
 *             description="Danh sách tier chưa được tạo"
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CreateTierRequest",
 *     type="object",
 *     required={"tier_id"},
 *     @OA\Property(property="tier_id", type="integer", enum={0, 1, 2, 3}, example=0, description="Mã tier cần tạo")
 * )
 *
 * @OA\Schema(
 *     schema="UpdateTierRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", example="Silver Plus", description="Tên tier mới")
 * )
 *
 * @OA\Schema(
 *     schema="AddExtraFeeRequest",
 *     type="object",
 *     required={"min_stitch", "max_stitch", "amount"},
 *     @OA\Property(property="min_stitch", type="integer", example=10000, description="Số stitch tối thiểu"),
 *     @OA\Property(property="max_stitch", type="integer", example=20000, description="Số stitch tối đa"),
 *     @OA\Property(property="amount", type="number", format="float", example=2.50, description="Số tiền phụ phí ($)")
 * )
 *
 * @OA\Schema(
 *     schema="AddRefundFeeRequest",
 *     type="object",
 *     required={"stitch", "amount"},
 *     @OA\Property(property="stitch", type="integer", example=5000, description="Số stitch"),
 *     @OA\Property(property="amount", type="number", format="float", example=1.00, description="Số tiền hoàn trả ($)")
 * )
 *
 * @OA\Schema(
 *     schema="AddEmbroideryFeeRequest",
 *     type="object",
 *     required={"embroidery_type", "min_stitch", "max_stitch", "amount"},
 *     @OA\Property(property="embroidery_type", type="string", enum={"metallic", "glow", "puff"}, example="metallic", description="Loại thêu"),
 *     @OA\Property(property="min_stitch", type="integer", example=0, description="Số stitch tối thiểu"),
 *     @OA\Property(property="max_stitch", type="integer", example=10000, description="Số stitch tối đa"),
 *     @OA\Property(property="amount", type="number", format="float", example=1.50, description="Số tiền phí thêu ($)")
 * )
 */
class TierSchemas
{
    // Chứa các schema definitions cho Tier endpoints
}
