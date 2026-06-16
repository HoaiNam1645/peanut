<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provider (ShipDVX/DNX) buy-label tracking fields on orders.
     * Existing shipping_label / tracking_id / shipping_service / shipping_json are reused.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('provider_order_id')->nullable()->after('shipping_json');       // provider _id
            $table->string('provider_order_number')->nullable()->index()->after('provider_order_id'); // orderNumber we send
            $table->string('provider_job_id')->nullable()->after('provider_order_number');  // jobId from create-orders
            $table->string('label_status')->nullable()->after('provider_job_id');           // provider lifecycle status
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'provider_order_id',
                'provider_order_number',
                'provider_job_id',
                'label_status',
            ]);
        });
    }
};
