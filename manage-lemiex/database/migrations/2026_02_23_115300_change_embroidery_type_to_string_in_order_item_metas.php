<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Change embroidery_type from ENUM('standard','metallic') to VARCHAR(100)
     * to support any embroidery type (3D, glow, puff, etc.)
     */
    public function up(): void
    {
        // Use raw SQL because Laravel doesn't support changing ENUM to VARCHAR directly
        DB::statement("ALTER TABLE `order_item_metas` MODIFY COLUMN `embroidery_type` VARCHAR(100) NULL DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting may lose data if values outside the ENUM exist
        DB::statement("ALTER TABLE `order_item_metas` MODIFY COLUMN `embroidery_type` ENUM('standard', 'metallic') NULL DEFAULT NULL");
    }
};
