<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_id')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'info_received', 'InfoReceived', 'in_transit', 'out_for_delivery', 'delivered', 'exception', 'not_found', 'Tracking...'])->default('pending');
            $table->enum('service', ['USPS', 'FedEx', 'UPS'])->default('USPS');
            $table->enum('method', ['standard', 'express', 'priority'])->default('standard');
            $table->integer('total_day')->nullable();
            $table->text('ssk')->nullable();
            $table->timestamp('update_time')->nullable();
            $table->timestamps();
            
            $table->index('tracking_id');
            $table->index('order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking');
    }
};
