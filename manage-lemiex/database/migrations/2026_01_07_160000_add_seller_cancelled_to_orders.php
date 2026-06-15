<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'seller_cancelled' to fulfill_status enum
     * This status is used when sellers cancel their unpaid new_order
     */
    public function up(): void
    {
        // Get current enum values
        $currentEnum = DB::select("SHOW COLUMNS FROM orders WHERE Field = 'fulfill_status'");

        if (!empty($currentEnum)) {
            $enumType = $currentEnum[0]->Type;

            // Check if new value already exists
            if (strpos($enumType, 'seller_cancelled') === false) {
                // Extract existing values and add new one
                // enum('value1','value2',...) -> get values
                preg_match("/^enum\((.*)\)$/", $enumType, $matches);

                if (!empty($matches[1])) {
                    $existingValues = $matches[1];
                    // Add new value before the closing parenthesis
                    $newEnum = "enum({$existingValues},'seller_cancelled')";

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
            ->where('fulfill_status', 'seller_cancelled')
            ->update(['fulfill_status' => 'cancelled']);

        // Get current enum values and remove the new one
        $currentEnum = DB::select("SHOW COLUMNS FROM orders WHERE Field = 'fulfill_status'");

        if (!empty($currentEnum)) {
            $enumType = $currentEnum[0]->Type;

            // Remove 'seller_cancelled' from enum
            $newEnumType = str_replace(",'seller_cancelled'", "", $enumType);
            $newEnumType = str_replace("'seller_cancelled',", "", $newEnumType);

            DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status {$newEnumType} DEFAULT 'new_order'");
        }
    }
};
