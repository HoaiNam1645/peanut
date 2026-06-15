<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_exchange', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('group_id');
            $table->string('group_name')->nullable();
            $table->decimal('get_amount', 10, 2)->nullable();
            $table->decimal('pay_amount', 10, 2)->nullable();
            $table->integer('rate')->nullable();
            $table->bigInteger('vnd')->nullable();
            $table->string('transaction_id')->nullable();
            $table->enum('type', ['usd', 'vnd', 'paypal'])->nullable();
            $table->timestamps();
            
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_exchange');
    }
};
