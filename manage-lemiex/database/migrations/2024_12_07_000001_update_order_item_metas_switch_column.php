<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change switch from tinyInteger to integer to store stitch count
        Schema::table('order_item_metas', function (Blueprint $table) {
            DB::statement("ALTER TABLE order_item_metas MODIFY COLUMN `switch` INT DEFAULT 0 COMMENT '0:Off|1:On|2:Special|Stitch Count'");
        });
    }

    public function down(): void
    {
        // Revert back to tinyInteger
        Schema::table('order_item_metas', function (Blueprint $table) {
            DB::statement("ALTER TABLE order_item_metas MODIFY COLUMN `switch` TINYINT DEFAULT 0 COMMENT '0:Off|1:On|2:Special'");
        });
    }
};
