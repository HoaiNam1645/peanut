<?php

namespace App\Swagger\Docs;

/**
 * ==================== STORE API DOCUMENTATION ====================
 * 
 * @OA\Get(
 *     path="/api/stores",
 *     operationId="getStores",
 *     tags={"Stores"},
 *     summary="Danh sách store đơn giản",
 *     description="Lấy danh sách store đơn giản cho dropdown hoặc tạo đơn. Admin thấy tất cả, Seller chỉ thấy store của mình.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(ref="#/components/schemas/StoreSimpleListResponse")
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/stores/list",
 *     operationId="getStoresList",
 *     tags={"Stores"},
 *     summary="Danh sách store có phân trang",
 *     description="Lấy danh sách store với phân trang và bộ lọc cho trang quản lý stores.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="per_page", in="query", description="Số lượng/trang", @OA\Schema(type="integer", default=10)),
 *     @OA\Parameter(name="page", in="query", description="Số trang", @OA\Schema(type="integer", default=1)),
 *     @OA\Parameter(name="search", in="query", description="Tìm theo tên hoặc API key", @OA\Schema(type="string")),
 *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string", enum={"Active", "Banned"})),
 *     @OA\Parameter(name="sort_by", in="query", description="Sắp xếp theo", @OA\Schema(type="string", default="created_at")),
 *     @OA\Parameter(name="sort_order", in="query", description="Thứ tự", @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(ref="#/components/schemas/StoreListResponse")
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/stores/users",
 *     operationId="getStoreUsers",
 *     tags={"Stores"},
 *     summary="Danh sách users cho dropdown",
 *     description="Lấy danh sách Sellers để chọn khi tạo store mới",
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Lấy danh sách users thành công"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/StoreUserItem")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Post(
 *     path="/api/stores",
 *     operationId="createStore",
 *     tags={"Stores"},
 *     summary="Tạo store mới",
 *     description="Tạo cửa hàng mới. API key phải là 32 ký tự bắt đầu bằng 'shpat_' và duy nhất.",
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin store",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="shopify_store",
 *                 summary="Tạo store Shopify",
 *                 value={
 *                     "user_id": 5,
 *                     "name": "My Shopify Store",
 *                     "api_key": "shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Tạo thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Tạo store thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/Store")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Validation lỗi",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=400),
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Validation failed"),
 *             @OA\Property(
 *                 property="errors",
 *                 type="object",
 *                 @OA\Property(property="name", type="array", @OA\Items(type="string", example="Tên store đã tồn tại")),
 *                 @OA\Property(property="api_key", type="array", @OA\Items(type="string", example="API key phải có định dạng shpat_xxxx... (32 ký tự)"))
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
 * )
 *
 * @OA\Put(
 *     path="/api/stores/{id}",
 *     operationId="updateStore",
 *     tags={"Stores"},
 *     summary="Cập nhật store",
 *     description="Cập nhật thông tin cửa hàng. Chỉ gửi các field cần thay đổi.",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID store", @OA\Schema(type="integer")),
 *     @OA\RequestBody(
 *         required=true,
 *         description="Thông tin cần cập nhật - Chọn example từ dropdown",
 *         @OA\JsonContent(
 *             @OA\Examples(
 *                 example="update_name",
 *                 summary="Đổi tên store",
 *                 value={"name": "New Store Name"}
 *             ),
 *             @OA\Examples(
 *                 example="update_api_key",
 *                 summary="Đổi API key",
 *                 value={"api_key": "shpat_yyyyyyyyyyyyyyyyyyyyyyyyyyyy"}
 *             ),
 *             @OA\Examples(
 *                 example="update_status",
 *                 summary="Đổi trạng thái (Ban/Active)",
 *                 value={"status": "Banned"}
 *             ),
 *             @OA\Examples(
 *                 example="update_owner",
 *                 summary="Đổi người sở hữu",
 *                 value={"user_id": 10}
 *             ),
 *             @OA\Examples(
 *                 example="update_full",
 *                 summary="Cập nhật đầy đủ",
 *                 value={
 *                     "name": "Updated Store Name",
 *                     "api_key": "shpat_zzzzzzzzzzzzzzzzzzzzzzzzzzzz",
 *                     "status": "Active",
 *                     "user_id": 10
 *                 }
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Cập nhật thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Cập nhật store thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/Store")
 *         )
 *     ),
 *     @OA\Response(response=400, description="Validation lỗi", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy store", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 *
 * @OA\Get(
 *     path="/api/stores/{id}",
 *     operationId="getStoreById",
 *     tags={"Stores"},
 *     summary="Chi tiết store",
 *     description="Lấy thông tin chi tiết một store theo ID",
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, description="ID store", @OA\Schema(type="integer")),
 *     @OA\Response(
 *         response=200,
 *         description="Thành công",
 *         @OA\JsonContent(
 *             @OA\Property(property="code", type="integer", example=200),
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Lấy thông tin store thành công"),
 *             @OA\Property(property="data", ref="#/components/schemas/Store")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Chưa đăng nhập", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
 *     @OA\Response(response=404, description="Không tìm thấy store", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
 * )
 */
class StoreDocs
{
    // Chứa Swagger documentation cho Store endpoints
    // Logic thực tế trong StoreController
}
