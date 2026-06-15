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
        // Add 'closed' to enum
        $newEnumValues = "'new_order','producing','qc_pass','packed','confirm','updated','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','in_stock','closed','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$newEnumValues}) NOT NULL DEFAULT 'new_order'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'closed' from enum (revert to previous step)
        // Convert any 'closed' orders to 'cancelled' before removing
        DB::table('orders')
            ->where('fulfill_status', 'closed')
            ->update(['fulfill_status' => 'cancelled']);

        $oldEnumValues = "'new_order','producing','qc_pass','packed','confirm','updated','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','in_stock','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$oldEnumValues}) NOT NULL DEFAULT 'new_order'");
    }
};
