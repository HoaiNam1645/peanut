<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timeline', function (Blueprint $table) {
            $table->id();
            $table->enum('object', ['order', 'ticket', 'user', 'product', 'transaction']);
            $table->unsignedBigInteger('object_id');
            $table->unsignedInteger('owner_id')->nullable();
            $table->string('action', 100);
            $table->text('note')->nullable();
            $table->timestamps();
            
            $table->index('object');
            $table->index('object_id');
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timeline');
    }
};
