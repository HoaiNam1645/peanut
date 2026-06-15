<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained()->onDelete('cascade');
            $table->string('image_path', 500);
            $table->string('image_name');
            $table->timestamps();
            
            $table->index('design_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_images');
    }
};
