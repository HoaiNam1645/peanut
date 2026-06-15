<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_fee', function (Blueprint $table) {
            $table->id();
            $table->integer('tier_id');
            $table->integer('min_stitch');
            $table->integer('max_stitch');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            
            $table->index('tier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_fee');
    }
};
