<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('group')->nullable()->after('name')->comment('Permission group for UI organization');
            $table->string('route')->nullable()->after('group')->comment('API route pattern');
            $table->string('method')->nullable()->after('route')->comment('HTTP method');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['group', 'route', 'method']);
        });
    }
};
