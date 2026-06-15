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
     * Loại bỏ 'seller_cancelled' khỏi enum fulfill_status
     * Chuyển các orders có status đó sang 'cancelled'
     */
    public function up(): void
    {
        // Step 1: Convert any existing 'seller_cancelled' orders to 'cancelled'
        DB::table('orders')
            ->where('fulfill_status', 'seller_cancelled')
            ->update(['fulfill_status' => 'cancelled']);

        // Step 2: Modify the enum to remove seller_cancelled
        // New enum values (without seller_cancelled)
        $newEnumValues = "'new_order','producing','qc_pass','packed','confirm','updated','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','in_stock','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$newEnumValues}) NOT NULL DEFAULT 'new_order'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back seller_cancelled to enum
        $oldEnumValues = "'new_order','producing','qc_pass','packed','confirm','updated','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','seller_cancelled','in_stock','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$oldEnumValues}) NOT NULL DEFAULT 'new_order'");
    }
};
