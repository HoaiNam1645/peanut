<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update the action enum to include new action types
        DB::statement("ALTER TABLE stock_audit_logs MODIFY COLUMN action ENUM(
            'increase', 
            'decrease', 
            'adjust', 
            'import',
            'update_sku',
            'update_style',
            'activate',
            'deactivate',
            'bulk_update'
        ) NOT NULL");
        
        // Make before_quantity and after_quantity nullable
        // since non-stock changes (SKU, Style, Active) don't have quantities
        Schema::table('stock_audit_logs', function (Blueprint $table) {
            $table->integer('before_quantity')->nullable()->change();
            $table->integer('after_quantity')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE stock_audit_logs MODIFY COLUMN action ENUM(
            'increase', 
            'decrease', 
            'adjust', 
            'import'
        ) NOT NULL");
        
        // Revert nullable changes
        Schema::table('stock_audit_logs', function (Blueprint $table) {
            $table->integer('before_quantity')->nullable(false)->change();
            $table->integer('after_quantity')->nullable(false)->change();
        });
    }
};
