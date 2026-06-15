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
        Schema::create('order_item_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->enum('position', ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck']);
            $table->enum('stage', ['staff', 'qc', 'packing', 'shipout']);
            $table->boolean('completed')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Unique: mỗi position chỉ có 1 row per stage
            $table->unique(['order_item_id', 'position', 'stage'], 'order_item_workflow_unique');

            // Indexes for common queries
            $table->index(['order_item_id', 'position']);
            $table->index(['stage', 'completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_workflows');
    }
};
