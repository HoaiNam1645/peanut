<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'processing_status')) {
                $table->enum('processing_status', [
                    'creating',
                    'processing',
                    'pricing_completed',
                    'completed',
                    'failed',
                    'qr_failed',
                    'pricing_failed'
                ])->default('creating')->after('fulfill_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'processing_status')) {
                $table->dropColumn('processing_status');
            }
        });
    }
};
