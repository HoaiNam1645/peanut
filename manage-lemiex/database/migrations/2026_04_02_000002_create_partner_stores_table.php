<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('total_order')->default(0);
            $table->string('status')->default('Active');
            $table->string('account_no')->nullable();
            $table->foreignId('partner_app_id')->nullable()->constrained('partner_apps')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('partner_app_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_stores');
    }
};
