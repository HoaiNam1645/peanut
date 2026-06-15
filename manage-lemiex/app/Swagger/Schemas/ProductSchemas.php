<?php

namespace App\Swagger\Schemas;

/**
 * ==================== PRODUCT SCHEMAS ====================
 * 
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     description="Thông tin sản phẩm",
 *     @OA\Property(property="id", type="integer", example=1, description="ID sản phẩm"),
 *     @OA\Property(property="name", type="string", example="Gildan 64000", description="Tên sản phẩm"),
 *     @OA\Property(property="style", type="string", nullable=true, example="64000", description="Mã style"),
 *     @OA\Property(property="category_type", type="string", nullable=true, example="embroidery", description="Loại danh mục (embroidery/print)"),
 *     @OA\Property(property="brand", type="string", nullable=true, example="Gildan", description="Thương hiệu"),
 *     @OA\Property(property="mockup", type="string", nullable=true, format="url", description="URL mockup"),
 *     @OA\Property(property="template_url", type="string", nullable=true, format="url", description="URL template sản phẩm"),
 *     @OA\Property(property="warehouse_name", type="string", nullable=true, example="US Warehouse", description="Tên kho"),
 *     @OA\Property(property="status", type="boolean", example=true, description="Trạng thái hoạt động"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="ProductVariant",
 *     type="object",
 *     description="Thông tin biến thể sản phẩm",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="variant_id", type="string", example="GILDAN-64000-BLACK-L", description="Mã variant (duy nhất)"),
 *     @OA\Property(property="sku", type="string", nullable=true, example="SKU-001", description="Mã SKU"),
 *     @OA\Property(property="style", type="string", nullable=true, example="64000"),
 *     @OA\Property(property="color", type="string", nullable=true, example="Black", description="Màu sắc"),
 *     @OA\Property(property="size", type="string", nullable=true, example="L", description="Kích cỡ"),
 *     @OA\Property(property="stock", type="integer", example=100, description="Tồn kho"),
 *     @OA\Property(property="active", type="boolean", example=true, description="Đang hoạt động"),
 *     @OA\Property(property="weight", type="integer", nullable=true, example=200, description="Trọng lượng (gram)"),
 *     @OA\Property(property="length", type="integer", nullable=true, example=30),
 *     @OA\Property(property="width", type="integer", nullable=true, example=20),
 *     @OA\Property(property="height", type="integer", nullable=true, example=5),
 *     @OA\Property(property="supplier_price", type="number", format="float", nullable=true, example=5.99, description="Giá nhà cung cấp")
 * )
 *
 * @OA\Schema(
 *     schema="ProductPriceVariant",
 *     type="object",
 *     description="Giá theo tier và loại",
 *     @OA\Property(property="tier_id", type="integer", enum={0, 1, 2, 3}, example=1, description="0=Silver, 1=Gold, 2=Platinum, 3=Diamond"),
 *     @OA\Property(
 *         property="type", 
 *         type="string", 
 *         enum={"base_cost", "front", "back", "sleeve_left", "sleeve_right", "special", "seller_shipping", "tiktok_shipping", "priority_shipping", "additional_standard", "additional_priority", "shipping_cost"},
 *         example="base_cost",
 *         description="Loại giá"
 *     ),
 *     @OA\Property(property="price", type="number", format="float", example=5.99)
 * )
 *
 * @OA\Schema(
 *     schema="ProductCreateRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", example="Gildan 64000", description="Tên sản phẩm"),
 *     @OA\Property(property="style", type="string", nullable=true, example="64000"),
 *     @OA\Property(property="category_type", type="string", nullable=true, example="embroidery", description="embroidery/print"),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="mockup", type="string", format="url", nullable=true),
 *     @OA\Property(property="template_url", type="string", format="url", nullable=true),
 *     @OA\Property(property="brand", type="string", nullable=true),
 *     @OA\Property(property="warehouse_name", type="string", nullable=true),
 *     @OA\Property(
 *         property="variants",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(
 *             type="object",
 *             required={"variant_id"},
 *             @OA\Property(property="variant_id", type="string", example="GILDAN-64000-BLACK-L"),
 *             @OA\Property(property="sku", type="string", nullable=true),
 *             @OA\Property(property="style", type="string", nullable=true),
 *             @OA\Property(property="color", type="string", nullable=true),
 *             @OA\Property(property="size", type="string", nullable=true),
 *             @OA\Property(property="stock", type="integer", example=100),
 *             @OA\Property(property="active", type="boolean", example=true),
 *             @OA\Property(property="supplier_price", type="number", format="float", nullable=true),
 *             @OA\Property(
 *                 property="prices",
 *                 type="array",
 *                 nullable=true,
 *                 @OA\Items(ref="#/components/schemas/ProductPriceVariant")
 *             )
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ProductUpdateRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", example="Gildan 64000 Updated"),
 *     @OA\Property(property="style", type="string", nullable=true),
 *     @OA\Property(property="category_type", type="string", nullable=true, example="embroidery"),
 *     @OA\Property(property="status", type="boolean"),
 *     @OA\Property(property="mockup", type="string", format="url", nullable=true),
 *     @OA\Property(property="template_url", type="string", format="url", nullable=true),
 *     @OA\Property(property="brand", type="string", nullable=true),
 *     @OA\Property(property="warehouse_name", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="VariantUpdateRequest",
 *     type="object",
 *     required={"variant_id"},
 *     @OA\Property(property="variant_id", type="string", example="GILDAN-64000-BLACK-L"),
 *     @OA\Property(property="sku", type="string", nullable=true),
 *     @OA\Property(property="style", type="string", nullable=true),
 *     @OA\Property(property="color", type="string", nullable=true),
 *     @OA\Property(property="size", type="string", nullable=true),
 *     @OA\Property(property="stock", type="integer", example=100),
 *     @OA\Property(property="active", type="boolean"),
 *     @OA\Property(property="weight", type="integer", nullable=true),
 *     @OA\Property(property="length", type="integer", nullable=true),
 *     @OA\Property(property="width", type="integer", nullable=true),
 *     @OA\Property(property="height", type="integer", nullable=true),
 *     @OA\Property(property="supplier_price", type="number", format="float", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="VariantPricingUpdateRequest",
 *     type="object",
 *     required={"prices"},
 *     @OA\Property(
 *         property="prices",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/ProductPriceVariant")
 *     )
 * )
 */
class ProductSchemas
{
    // Chứa các schema definitions cho Product endpoints
}
