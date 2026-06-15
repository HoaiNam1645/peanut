<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['Unconfirmed', 'Active', 'Banned'])->default('Unconfirmed');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->rememberToken();
            $table->string('api_key')->unique()->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('username');
            $table->index('status');
            $table->index('created_at');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
