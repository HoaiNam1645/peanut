<?php

namespace App\Swagger\Seller;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Wecat Seller API",
 *     description="API Documentation dành cho Seller - Tạo đơn hàng, theo dõi đơn hàng",
 *     @OA\Contact(
 *         email="support@wecat.com",
 *         name="Wecat Support"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="JWT Token từ API đăng nhập. Ví dụ: 'Bearer {token}'"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Dang nhap, dang xuat, thong tin user"
 * )
 * @OA\Tag(
 *     name="Product - Print",
 *     description="San pham in - Lay styles, sizes, variants"
 * )
 * @OA\Tag(
 *     name="Create Order",
 *     description="Tao don hang in (Label Ship / Seller Ship)"
 * )
 * @OA\Tag(
 *     name="Update Order",
 *     description="Cap nhat don hang go - chi sua duoc khi don o trang thai cho phep"
 * )
 * @OA\Tag(
 *     name="Track Order",
 *     description="Theo doi trang thai va chi tiet don hang"
 * )
 * @OA\Tag(
 *     name="Webhook",
 *     description="Webhook thong bao khi don hang thay doi trang thai"
 * )
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Thong ke dashboard cua seller"
 * )
 * @OA\Tag(
 *     name="Stores",
 *     description="Quan ly cua hang cua seller"
 * )
 * @OA\Tag(
 *     name="Catalog",
 *     description="Danh muc san pham + bien the (variants)"
 * )
 * @OA\Tag(
 *     name="Tickets",
 *     description="Khieu nai / Ticket ho tro"
 * )
 * @OA\Tag(
 *     name="Wallet",
 *     description="Vi seller - nap tien, lich su giao dich"
 * )
 * @OA\Tag(
 *     name="Tiers",
 *     description="Xem cap bac seller hien tai"
 * )
 */
class OpenApiSpec
{
    // Class container cho Seller OpenAPI annotations
}
