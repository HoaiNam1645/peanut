<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('embroidery_fee', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('tier_id')->index();
            $table->string('embroidery_type', 50)->index(); // standard, metallic, glow, etc.
            $table->integer('min_stitch')->default(0);
            $table->integer('max_stitch')->default(999999);
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->timestamps();

            $table->index(['tier_id', 'embroidery_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('embroidery_fee');
    }
};
