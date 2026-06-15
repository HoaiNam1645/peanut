<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add 'Tumbler' to order_type enum for print orders
     */
    public function up(): void
    {
        // MySQL: ALTER to modify enum values
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('Shirt', 'Poster', 'Cotton tote bag', 'Jute Tote Bag', 'Tumbler') DEFAULT 'Shirt'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'Tumbler' from enum (only if no orders use it)
        DB::statement("ALTER TABLE orders MODIFY COLUMN order_type ENUM('Shirt', 'Poster', 'Cotton tote bag', 'Jute Tote Bag') DEFAULT 'Shirt'");
    }
};
