<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->enum('meta_key', ['front_design', 'back_design', 'sleeve_left_design', 'sleeve_right_design', 'special_design', 'design_file', 'front_design_qr', 'back_design_qr', 'sleeve_left_design_qr', 'sleeve_right_design_qr', 'special_design_qr']);
            $table->longText('meta_value')->nullable();
            $table->boolean('append_qr_design')->default(false);
            $table->boolean('overide_qr')->default(false);
            $table->enum('oversize_site', ['left', 'right', 'center'])->nullable();
            $table->tinyInteger('switch')->default(0)->comment('0:Off|1:On|2:Special');
            $table->boolean('status')->default(false);
            $table->timestamp('update_time')->nullable();
            $table->timestamps();
            
            $table->index('order_item_id');
            $table->index('meta_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_metas');
    }
};
