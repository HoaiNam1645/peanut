<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Move any 'updated' orders to 'confirm'
        DB::table('orders')
            ->where('fulfill_status', 'updated')
            ->update(['fulfill_status' => 'confirm']);

        // 2. Remove 'updated' from enum
        // Current valid statuses: new_order, producing, qc_pass, packed, confirm, pending_stock, on_hold, shipped, return_to_support, cancelled, cancelled_refund_shipping, in_stock, closed, test_order
        $newEnumValues = "'new_order','producing','qc_pass','packed','confirm','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','in_stock','closed','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$newEnumValues}) NOT NULL DEFAULT 'new_order'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add 'updated' back
        $oldEnumValues = "'new_order','producing','qc_pass','packed','confirm','updated','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','in_stock','closed','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$oldEnumValues}) NOT NULL DEFAULT 'new_order'");
    }
};
