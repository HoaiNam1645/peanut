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
        // Create fulfillment_priorities table to store pricing per tier
        Schema::create('fulfillment_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50); // 'normal', 'priority'
            $table->string('display_name', 100); // 'Normal', 'Priority'
            $table->text('description')->nullable();
            $table->integer('tier_id')->default(0); // 0=Silver, 1=Gold, 2=Platinum, 3=Diamond
            $table->decimal('price', 10, 2)->default(0); // Price for this tier
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Unique constraint: each priority type has one price per tier
            $table->unique(['name', 'tier_id']);
        });

        // Update orders table
        Schema::table('orders', function (Blueprint $table) {
            // Add priority_fee column to store the fee charged
            $table->decimal('priority_fee', 10, 2)->default(0)->after('embroidery_fee');

            // Change column type from enum to string for flexibility
            $table->string('fulfillment_priority', 20)->default('normal')->change();
        });

        // Migrate existing data: change 'high' and 'urgent' to 'priority'
        \Illuminate\Support\Facades\DB::table('orders')
            ->whereIn('fulfillment_priority', ['high', 'urgent'])
            ->update(['fulfillment_priority' => 'priority']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_priorities');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('priority_fee');
            $table->enum('fulfillment_priority', ['normal', 'high', 'urgent'])
                ->default('normal')
                ->nullable()
                ->change();
        });
    }
};
