<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rebrand order_type enum from 'Wood' to 'Print' to match OrderType::PRINT.
     */
    public function up(): void
    {
        // Relax to varchar so existing values can be remapped safely
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type VARCHAR(50) DEFAULT NULL");
        DB::table('orders')->where('order_type', 'Wood')->update(['order_type' => 'Print']);
        // Lock back to a single-value enum, now 'Print'
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('Print') DEFAULT 'Print'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type VARCHAR(50) DEFAULT NULL");
        DB::table('orders')->where('order_type', 'Print')->update(['order_type' => 'Wood']);
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('Wood') DEFAULT 'Wood'");
    }
};
