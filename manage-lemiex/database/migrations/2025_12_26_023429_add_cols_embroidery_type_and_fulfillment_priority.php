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
        Schema::table('order_item_metas', function (Blueprint $table) {
            $table->enum('embroidery_type', ['standard', 'metallic'])
                ->default('standard')
                ->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('fulfillment_priority', ['normal', 'high', 'urgent'])
                ->default('normal')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_item_metas', function (Blueprint $table) {
            $table->dropColumn('embroidery_type');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fulfillment_priority');
        });
    }
};
