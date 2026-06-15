<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('seller_id');
            $table->date('date');
            $table->integer('total_orders')->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0.00);
            $table->timestamps();
            
            $table->index('seller_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sellers');
    }
};
