<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'cancelled_refund_shipping' to fulfill_status enum
     */
    public function up(): void
    {
        // Get current enum values
        $currentEnum = DB::select("SHOW COLUMNS FROM orders WHERE Field = 'fulfill_status'");

        if (!empty($currentEnum)) {
            $enumType = $currentEnum[0]->Type;

            // Check if new value already exists
            if (strpos($enumType, 'cancelled_refund_shipping') === false) {
                // Extract existing values and add new one
                // enum('value1','value2',...) -> get values
                preg_match("/^enum\((.*)\)$/", $enumType, $matches);

                if (!empty($matches[1])) {
                    $existingValues = $matches[1];
                    // Add new value before the closing parenthesis
                    $newEnum = "enum({$existingValues},'cancelled_refund_shipping')";

                    DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status {$newEnum} DEFAULT 'new_order'");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any orders with the new status back to cancelled
        DB::table('orders')
            ->where('fulfill_status', 'cancelled_refund_shipping')
            ->update(['fulfill_status' => 'cancelled']);

        // Get current enum values and remove the new one
        $currentEnum = DB::select("SHOW COLUMNS FROM orders WHERE Field = 'fulfill_status'");

        if (!empty($currentEnum)) {
            $enumType = $currentEnum[0]->Type;

            // Remove 'cancelled_refund_shipping' from enum
            $newEnumType = str_replace(",'cancelled_refund_shipping'", "", $enumType);
            $newEnumType = str_replace("'cancelled_refund_shipping',", "", $newEnumType);

            DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status {$newEnumType} DEFAULT 'new_order'");
        }
    }
};
