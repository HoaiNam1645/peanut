<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'template_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('template_url', 1000)->nullable()->after('mockup');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'template_url')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('template_url');
            });
        }
    }
};
