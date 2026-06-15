<?php

namespace App\Swagger\Docs;

/**
 * @OA\Get(
 *     path="/api/fulfillment-priorities",
 *     operationId="getAllFulfillmentPriorities",
 *     tags={"Fulfillment"},
 *     summary="Danh sách các mức ưu tiên fulfillment",
 *     description="Yêu cầu fulfillment.view.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/fulfillment-priorities/tier",
 *     operationId="getFulfillmentForTier",
 *     tags={"Fulfillment"},
 *     summary="Mức ưu tiên fulfillment khả dụng cho tier hiện tại",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/fulfillment-priorities/price",
 *     operationId="getFulfillmentPrice",
 *     tags={"Fulfillment"},
 *     summary="Lấy giá fulfillment theo tier + priority",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="tier_id", in="query", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="priority", in="query", required=true, @OA\Schema(type="string"), description="Mã priority"),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Put(
 *     path="/api/fulfillment-priorities",
 *     operationId="updateFulfillmentPriorities",
 *     tags={"Fulfillment"},
 *     summary="Cập nhật danh sách priorities",
 *     description="Yêu cầu fulfillment.manage.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"priorities"},
 *             @OA\Property(
 *                 property="priorities",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="name", type="string", example="standard"),
 *                     @OA\Property(property="display_name", type="string", example="Tiêu chuẩn"),
 *                     @OA\Property(property="active", type="boolean", example=true)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cập nhật thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/tiers/{tierId}/priority-fee",
 *     operationId="getTierPriorityFees",
 *     tags={"Fulfillment"},
 *     summary="Danh sách phí ưu tiên cho 1 tier",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Post(
 *     path="/api/tiers/{tierId}/priority-fee",
 *     operationId="addTierPriorityFee",
 *     tags={"Fulfillment"},
 *     summary="Thêm mức phí ưu tiên cho tier",
 *     description="Yêu cầu tiers.manage.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"priority", "price"},
 *             @OA\Property(property="priority", type="string", example="express"),
 *             @OA\Property(property="price", type="number", format="float", example=5.99)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Thêm thành công")
 * )
 *
 * @OA\Put(
 *     path="/api/tiers/{tierId}/priority-fee/{id}",
 *     operationId="updateTierPriorityFee",
 *     tags={"Fulfillment"},
 *     summary="Cập nhật phí ưu tiên",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="price", type="number", format="float")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Delete(
 *     path="/api/tiers/{tierId}/priority-fee/{id}",
 *     operationId="deleteTierPriorityFee",
 *     tags={"Fulfillment"},
 *     summary="Xoá phí ưu tiên",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="tierId", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Xoá thành công")
 * )
 */
class FulfillmentPriorityDocs
{
}
