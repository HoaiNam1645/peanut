<?php

namespace App\Swagger\Seller\Docs;

/**
 * @OA\Get(
 *     path="/api/stores",
 *     operationId="sellerListStores",
 *     tags={"Stores"},
 *     summary="Danh sách cửa hàng của seller",
 *     description="Seller chỉ thấy stores của mình. Yêu cầu stores.view.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active", "inactive"})),
 *     @OA\Response(response=200, description="Thành công"),
 *     @OA\Response(response=403, description="Không có quyền stores.view")
 * )
 *
 * @OA\Get(
 *     path="/api/stores/list",
 *     operationId="sellerGetStoresList",
 *     tags={"Stores"},
 *     summary="Danh sách store rút gọn (dùng cho dropdown)",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Thành công")
 * )
 *
 * @OA\Get(
 *     path="/api/stores/{id}",
 *     operationId="sellerGetStoreDetail",
 *     tags={"Stores"},
 *     summary="Chi tiết một store",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Thành công"),
 *     @OA\Response(response=404, description="Store không tồn tại")
 * )
 *
 * @OA\Post(
 *     path="/api/stores",
 *     operationId="sellerCreateStore",
 *     tags={"Stores"},
 *     summary="Tạo cửa hàng mới",
 *     description="Yêu cầu stores.create.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name", "platform"},
 *             @OA\Property(property="name", type="string", example="My Shopify Store"),
 *             @OA\Property(property="platform", type="string", enum={"shopify", "tiktok", "etsy", "amazon", "other"}),
 *             @OA\Property(property="domain", type="string", example="myshop.myshopify.com"),
 *             @OA\Property(property="api_key", type="string", description="API key của shop (nếu có integration)"),
 *             @OA\Property(property="webhook_url", type="string", description="URL nhận webhook"),
 *             @OA\Property(property="note", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Tạo store thành công"),
 *     @OA\Response(response=400, description="Validation lỗi")
 * )
 *
 * @OA\Put(
 *     path="/api/stores/{id}",
 *     operationId="sellerUpdateStore",
 *     tags={"Stores"},
 *     summary="Cập nhật store",
 *     description="Yêu cầu stores.update.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="domain", type="string"),
 *             @OA\Property(property="api_key", type="string"),
 *             @OA\Property(property="webhook_url", type="string"),
 *             @OA\Property(property="status", type="string", enum={"active", "inactive"}),
 *             @OA\Property(property="note", type="string")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Cập nhật thành công")
 * )
 */
class StoreDocs
{
    // Class container for Seller Store API documentation
}
