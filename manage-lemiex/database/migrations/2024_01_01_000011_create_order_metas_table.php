<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_metas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('object_id');
            $table->string('meta_key');
            $table->text('meta_value')->nullable();
            $table->timestamps();
            
            $table->index('object_id');
            $table->index('meta_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_metas');
    }
};
