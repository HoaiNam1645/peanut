<?php

namespace App\Swagger\Seller\Docs;

/**
 * @OA\Get(
 *     path="/api/tiers",
 *     operationId="sellerListTiers",
 *     tags={"Tiers"},
 *     summary="Danh sách tiers (cấp bậc seller)",
 *     description="Xem thông tin các tier kèm bảng giá fulfillment, embroidery fee... Yêu cầu tiers.view.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/tiers/options",
 *     operationId="sellerGetTierOptions",
 *     tags={"Tiers"},
 *     summary="Danh sách tier rút gọn (dropdown)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 */
class TierDocs
{
    // Class container for Seller Tier API documentation
}
