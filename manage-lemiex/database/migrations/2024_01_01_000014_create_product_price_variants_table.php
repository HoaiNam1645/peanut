<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_variants', function (Blueprint $table) {
            $table->id();
            $table->string('product_variant_id');
            $table->integer('tier_id')->comment('0:Silver|1:Gold|2:Platinum|3:Diamond');
            $table->enum('type', ['base_cost', 'front', 'back', 'sleeve_left', 'sleeve_right', 'special', 'seller_shipping', 'tiktok_shipping', 'priority_shipping', 'additional_standard', 'additional_priority', 'shipping_cost']);
            $table->decimal('price', 10, 2);
            $table->timestamps();
            
            $table->index('product_variant_id');
            $table->index('tier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_variants');
    }
};
