<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Exact moment the order left the workshop (the instant the shipping
            // label scan / shipout stage was completed). This is distinct from
            // created_at (when the order was placed) and is used to reconcile the
            // daily batch of shipped orders against the carrier's manifest.
            $table->timestamp('shipped_at')->nullable()->after('complete_time');
            $table->index('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipped_at']);
            $table->dropColumn('shipped_at');
        });
    }
};
