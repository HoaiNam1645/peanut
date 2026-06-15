<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('ref_id')->unique()->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('seller_ref')->nullable();
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('set null');
            $table->text('shipping_label')->nullable();
            $table->enum('shipping_service', ['USPS', 'FedEx', 'UPS'])->nullable();
            $table->enum('shipping_method', ['standard', 'express', 'priority'])->nullable();
            $table->text('shipping_json')->nullable();
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->string('tracking_id')->nullable();
            $table->string('tracking_link')->nullable();
            $table->enum('fulfill_status', ['new_order', 'producing', 'confirm', 'express', 'priority', 'updated', 'pending_stock', 'on_hold', 'shipped', 'return_to_support', 'cancelled', 'in_stock', 'test_order'])->default('new_order');
            $table->enum('payment_status', ['pending', 'paid', 'full_refund', 'partial_refund'])->default('pending');
            $table->decimal('total_cost', 10, 2)->nullable();
            $table->decimal('paid_cost', 10, 2)->nullable();
            $table->decimal('print_cost', 10, 2)->nullable();
            $table->decimal('extra_fee', 10, 2)->default(0.00);
            $table->decimal('refund_fee', 10, 2)->default(0.00);
            $table->text('convert_label')->nullable();
            $table->text('override_label')->nullable();
            $table->text('note')->nullable();
            $table->string('order_stt')->nullable();
            $table->enum('order_type', ['Shirt', 'Poster', 'Cotton tote bag', 'Jute Tote Bag'])->nullable();
            $table->timestamp('process_time')->nullable();
            $table->timestamp('complete_time')->nullable();
            $table->boolean('scan_early')->default(false);
            $table->text('merged_url')->nullable();
            $table->longText('post_json')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('fulfill_status');
            $table->index('payment_status');
            $table->index('seller_id');
            $table->index('order_type');
            $table->index('created_at');
            $table->index('deleted_at');
            
            $table->foreign('seller_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
