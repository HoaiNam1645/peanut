<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên quốc gia (VD: United States)
            $table->char('iso_code', 2)->unique(); // Mã ISO 2 ký tự (VD: US, VN)
            $table->string('currency_code', 3)->nullable(); // Mã tiền tệ (VD: USD, VND)
            $table->string('phone_code', 5)->nullable(); // Mã điện thoại (VD: +1, +84)
            $table->string('flag', 10)->nullable(); // Emoji cờ hoặc URL
            $table->boolean('active')->default(true); // Có hỗ trợ shipping không
            
            $table->index('iso_code');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
