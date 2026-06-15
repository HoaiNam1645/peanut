<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_stock_id')->constrained()->onDelete('cascade');
            $table->string('variant_id');
            $table->integer('stock')->default(0);
            $table->integer('last_week_sale')->default(0);
            $table->string('status', 50)->nullable();
            $table->timestamps();
            
            $table->index('report_stock_id');
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_stock_items');
    }
};
