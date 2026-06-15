<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained('order_items')->onDelete('cascade');
            $table->text('note')->nullable();
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->integer('status')->default(1)->comment('1:New|2:OnHold|3:Processing|4:Done');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('item_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
