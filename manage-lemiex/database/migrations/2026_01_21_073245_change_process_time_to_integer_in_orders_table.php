<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change process_time from timestamp to integer (stores number of days to process order)
     */
    public function up(): void
    {
        // First, clear existing data (timestamp values can't be converted to int)
        DB::table('orders')->update(['process_time' => null]);

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('process_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('process_time')->nullable()->change();
        });
    }
};
