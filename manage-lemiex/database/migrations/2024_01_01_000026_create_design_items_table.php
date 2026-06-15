<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_id')->constrained()->onDelete('cascade');
            $table->string('file_path', 500);
            $table->string('file_name');
            $table->enum('file_type', ['emb', 'pes', 'dst'])->nullable();
            $table->enum('type', ['front', 'back', 'sleeve_left', 'sleeve_right', 'special'])->nullable();
            $table->timestamps();
            
            $table->index('design_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_items');
    }
};
