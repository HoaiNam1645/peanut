<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('subject');
            $table->text('image_link')->nullable();
            $table->boolean('status')->default(false)->comment('0:New|1:Solved');
            $table->unsignedInteger('user_solved')->nullable();
            $table->unsignedInteger('user_reply')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('user_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('deleted_at');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supports');
    }
};
