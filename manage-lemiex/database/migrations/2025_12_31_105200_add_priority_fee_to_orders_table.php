<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add priority_fee column to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'priority_fee')) {
                $table->decimal('priority_fee', 10, 2)->default(0)->after('embroidery_fee');
            }
        });

        // Migrate existing data: change 'high' and 'urgent' to 'priority'
        \Illuminate\Support\Facades\DB::table('orders')
            ->whereIn('fulfillment_priority', ['high', 'urgent'])
            ->update(['fulfillment_priority' => 'priority']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'priority_fee')) {
                $table->dropColumn('priority_fee');
            }
        });
    }
};
