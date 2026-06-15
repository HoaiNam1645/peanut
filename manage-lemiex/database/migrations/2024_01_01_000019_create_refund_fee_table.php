<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_fee', function (Blueprint $table) {
            $table->id();
            $table->integer('tier_id');
            $table->integer('stitch');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
            
            $table->index('tier_id');
            $table->unique(['tier_id', 'stitch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_fee');
    }
};
