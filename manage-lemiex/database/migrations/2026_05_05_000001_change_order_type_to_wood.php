<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Loosen column to VARCHAR so existing enum values don't block the update
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type VARCHAR(50) DEFAULT NULL");

        // Step 2: Migrate all existing data to 'Wood'
        DB::table('orders')->update(['order_type' => 'Wood']);

        // Step 3: Lock column back down to the new single-value enum
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('Wood') DEFAULT 'Wood'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type VARCHAR(50) DEFAULT NULL");
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('Shirt', 'Poster', 'Cotton tote bag', 'Jute Tote Bag', 'Tumbler') DEFAULT NULL");
    }
};
