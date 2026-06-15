<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->string('product_variant_id');
            $table->decimal('quantity', 10, 2);
            $table->enum('status', ['pending', 'mapped', 'completed', 'canceled', 'pickup'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();
            
            $table->index('order_item_id');
            $table->index('product_variant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
