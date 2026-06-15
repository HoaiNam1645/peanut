<?php

namespace App\Swagger;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Wecat Fulfillment API",
 *     description="Tài liệu API cho hệ thống Wecat Fulfillment - Quản lý đơn hàng, sản phẩm, kho hàng và thanh toán",
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
 *     description="Xác thực JWT. Sử dụng token từ API đăng nhập. Ví dụ: 'Bearer {token}'"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Xác thực - Đăng nhập, đăng xuất, thông tin người dùng"
 * )
 * @OA\Tag(
 *     name="Users",
 *     description="Quản lý người dùng (Chỉ Admin)"
 * )
 * @OA\Tag(
 *     name="Orders",
 *     description="Quản lý đơn hàng - Tạo, cập nhật, theo dõi đơn hàng"
 * )
 * @OA\Tag(
 *     name="Stores",
 *     description="Quản lý cửa hàng"
 * )
 * @OA\Tag(
 *     name="Products",
 *     description="Quản lý sản phẩm và biến thể"
 * )
 * @OA\Tag(
 *     name="Stock",
 *     description="Quản lý tồn kho"
 * )
 * @OA\Tag(
 *     name="Dashboard",
 *     description="Thống kê và báo cáo"
 * )
 * @OA\Tag(
 *     name="Transactions",
 *     description="Ví và giao dịch thanh toán"
 * )
 * @OA\Tag(
 *     name="Tickets",
 *     description="Hỗ trợ khách hàng - Ticket"
 * )
 * @OA\Tag(
 *     name="Tiers",
 *     description="Quản lý cấp bậc seller"
 * )
 * @OA\Tag(
 *     name="Buy Label",
 *     description="Mua nhãn vận chuyển"
 * )
 * @OA\Tag(
 *     name="Permissions",
 *     description="Quản lý phân quyền - permissions và roles (Yêu cầu permissions.manage)"
 * )
 * @OA\Tag(
 *     name="Attendance",
 *     description="Chấm công - log giờ làm, import từ máy chấm công"
 * )
 * @OA\Tag(
 *     name="Payroll",
 *     description="Bảng lương - tier, salary, điều chỉnh thưởng/phạt"
 * )
 * @OA\Tag(
 *     name="Fulfillment",
 *     description="Mức độ ưu tiên fulfillment và phí theo tier"
 * )
 * @OA\Tag(
 *     name="Reports",
 *     description="Báo cáo - hiệu suất nhân viên, thiếu hàng, dashboard kho"
 * )
 *
 * @OA\Response(
 *     response="Forbidden",
 *     description="User không có permission cần thiết để truy cập endpoint này",
 *     @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")
 * )
 */
class OpenApiSpec
{
    // Class container cho OpenAPI annotations
}
