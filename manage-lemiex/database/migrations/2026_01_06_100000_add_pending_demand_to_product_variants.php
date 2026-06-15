<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add pending_demand column to track total quantity needed from pending_stock orders.
     * This enables quick shortage reporting without complex joins.
     * 
     * Formula:
     * - shortage = pending_demand - stock (when positive = shortage)
     * - If stock = 10, pending_demand = 5 → sufficient stock
     * - If stock = 5, pending_demand = 20 → shortage of 15
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('pending_demand')->default(0)->after('stock')
                ->comment('Total quantity needed from pending_stock orders');

            // Index for fast shortage queries
            $table->index(['pending_demand', 'stock'], 'idx_shortage_report');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('idx_shortage_report');
            $table->dropColumn('pending_demand');
        });
    }
};
