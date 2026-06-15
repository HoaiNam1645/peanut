<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change meta_key from enum to string for flexibility
        Schema::table('order_item_metas', function (Blueprint $table) {
            DB::statement("ALTER TABLE order_item_metas MODIFY COLUMN meta_key VARCHAR(255)");
        });
    }

    public function down(): void
    {
        // Revert back to enum (not recommended as data may be lost)
        Schema::table('order_item_metas', function (Blueprint $table) {
            DB::statement("ALTER TABLE order_item_metas MODIFY COLUMN meta_key ENUM('front_design', 'back_design', 'sleeve_left_design', 'sleeve_right_design', 'special_design', 'design_file', 'front_design_qr', 'back_design_qr', 'sleeve_left_design_qr', 'sleeve_right_design_qr', 'special_design_qr')");
        });
    }
};
