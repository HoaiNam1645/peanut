<?php

namespace App\Swagger\Schemas;

/**
 * ==================== STORE SCHEMAS ====================
 * 
 * @OA\Schema(
 *     schema="Store",
 *     type="object",
 *     description="Thông tin cửa hàng",
 *     @OA\Property(property="id", type="integer", example=1, description="ID store"),
 *     @OA\Property(property="user_id", type="integer", example=5, description="ID người sở hữu"),
 *     @OA\Property(property="name", type="string", example="My Shopify Store", description="Tên cửa hàng"),
 *     @OA\Property(property="api_key", type="string", example="shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", description="API key (32 ký tự)"),
 *     @OA\Property(property="status", type="string", enum={"Active", "Banned"}, example="Active", description="Trạng thái"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Ngày tạo"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Ngày cập nhật"),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         nullable=true,
 *         description="Thông tin người sở hữu",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="username", type="string"),
 *         @OA\Property(property="email", type="string")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="StoreListResponse",
 *     type="object",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Lấy danh sách store thành công"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(
 *             property="stores",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/Store")
 *         ),
 *         @OA\Property(
 *             property="pagination",
 *             type="object",
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=10),
 *             @OA\Property(property="total", type="integer", example=50),
 *             @OA\Property(property="last_page", type="integer", example=5)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="StoreSimpleListResponse",
 *     type="object",
 *     description="Danh sách store đơn giản cho dropdown",
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Lấy danh sách store thành công"),
 *     @OA\Property(
 *         property="data",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Store")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="StoreCreateRequest",
 *     type="object",
 *     required={"user_id", "name", "api_key"},
 *     @OA\Property(property="user_id", type="integer", example=5, description="ID người sở hữu store"),
 *     @OA\Property(property="name", type="string", example="My Shopify Store", description="Tên cửa hàng (duy nhất)"),
 *     @OA\Property(
 *         property="api_key", 
 *         type="string", 
 *         example="shpat_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", 
 *         description="API key (32 ký tự bắt đầu bằng shpat_)"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="StoreUpdateRequest",
 *     type="object",
 *     @OA\Property(property="user_id", type="integer", example=5, description="ID người sở hữu mới"),
 *     @OA\Property(property="name", type="string", example="Updated Store Name", description="Tên cửa hàng mới"),
 *     @OA\Property(property="api_key", type="string", example="shpat_yyyyyyyyyyyyyyyyyyyyyyyyyyyy", description="API key mới"),
 *     @OA\Property(property="status", type="string", enum={"Active", "Banned"}, example="Active", description="Trạng thái mới")
 * )
 *
 * @OA\Schema(
 *     schema="StoreUserItem",
 *     type="object",
 *     description="User item cho dropdown tạo store",
 *     @OA\Property(property="id", type="integer", example=5),
 *     @OA\Property(property="username", type="string", example="seller_gold"),
 *     @OA\Property(property="email", type="string", example="seller@example.com")
 * )
 */
class StoreSchemas
{
    // Chứa các schema definitions cho Store endpoints
}
