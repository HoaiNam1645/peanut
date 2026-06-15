<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('auth_url')->nullable();
            $table->string('proxy_status')->default('live');
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        DB::table('partner_apps')->insert([
            'name' => 'Pressify',
            'slug' => 'pressify',
            'auth_url' => env('PARTNER_APP_PRESSIFY_AUTH_URL'),
            'proxy_status' => 'live',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_apps');
    }
};
