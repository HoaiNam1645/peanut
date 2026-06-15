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
     * Loại bỏ 'express' và 'priority' khỏi enum fulfill_status
     * Chuyển các orders có status đó sang 'producing'
     */
    public function up(): void
    {
        // Step 1: Convert any existing 'express' or 'priority' orders to 'producing'
        DB::table('orders')
            ->whereIn('fulfill_status', ['express', 'priority'])
            ->update(['fulfill_status' => 'producing']);

        // Step 2: Modify the enum to remove express and priority
        // New enum values (without express and priority)
        $newEnumValues = "'new_order','producing','qc_pass','packed','confirm','updated','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','seller_cancelled','in_stock','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$newEnumValues}) NOT NULL DEFAULT 'new_order'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back express and priority to enum
        $oldEnumValues = "'new_order','producing','qc_pass','packed','confirm','express','priority','updated','pending_stock','on_hold','shipped','return_to_support','cancelled','cancelled_refund_shipping','seller_cancelled','in_stock','test_order'";

        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM({$oldEnumValues}) NOT NULL DEFAULT 'new_order'");
    }
};
