<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('variant_id')->nullable();
            $table->text('product_name')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('status')->default(false);
            $table->text('mockup')->nullable();
            $table->text('mockup_back')->nullable();
            $table->text('pdf')->nullable();
            $table->integer('sides')->nullable();
            $table->string('id_style')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('order_id');
            $table->index('variant_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
