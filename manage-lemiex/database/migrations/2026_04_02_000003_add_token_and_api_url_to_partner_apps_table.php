<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_apps', function (Blueprint $table) {
            $table->text('token')->nullable()->after('auth_url');
            $table->string('api_url')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('partner_apps', function (Blueprint $table) {
            $table->dropColumn(['token', 'api_url']);
        });
    }
};
