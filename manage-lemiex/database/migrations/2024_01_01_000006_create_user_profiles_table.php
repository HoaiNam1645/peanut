<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->onDelete('set null');
            $table->date('birthday')->nullable();
            $table->integer('two_factor_country_code')->nullable();
            $table->unsignedBigInteger('two_factor_phone')->nullable();
            $table->text('two_factor_options')->nullable();
            $table->decimal('wallet_balance', 10, 2)->default(0.00);
            $table->integer('private_seller')->default(0)->comment('0:Silver, 1:Gold, 2:Platinum, 3:Diamond');
            $table->string('webhook_url')->nullable();
            $table->bigInteger('telegram_id')->nullable();
            $table->boolean('is_support_us')->default(false);
            $table->decimal('max_debit', 10, 2)->default(0.00);
            $table->integer('max_date_debit')->nullable();
            $table->integer('min_date_debit')->nullable();
            $table->boolean('debit_status')->default(false);
            $table->boolean('production')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
