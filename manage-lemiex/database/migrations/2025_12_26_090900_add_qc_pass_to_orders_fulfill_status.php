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
        // Alter the fulfill_status enum to include new workflow statuses
        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM(
            'new_order',
            'producing',
            'qc_pass',
            'packed',
            'confirm',
            'express',
            'priority',
            'updated',
            'pending_stock',
            'on_hold',
            'shipped',
            'return_to_support',
            'cancelled',
            'in_stock',
            'test_order'
        ) DEFAULT 'new_order'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum (without qc_pass and packed if they weren't there)
        DB::statement("ALTER TABLE orders MODIFY COLUMN fulfill_status ENUM(
            'new_order',
            'producing',
            'confirm',
            'express',
            'priority',
            'updated',
            'pending_stock',
            'on_hold',
            'shipped',
            'return_to_support',
            'cancelled',
            'in_stock',
            'test_order'
        ) DEFAULT 'new_order'");
    }
};
