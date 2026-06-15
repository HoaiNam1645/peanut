<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: relax to VARCHAR to avoid enum constraint errors
        DB::statement("ALTER TABLE products MODIFY COLUMN category_type VARCHAR(50) DEFAULT NULL");

        // Step 2: migrate all existing rows to 'wood'
        DB::table('products')->update(['category_type' => 'wood']);

        // Step 3: lock back to ENUM('wood') only
        DB::statement("ALTER TABLE products MODIFY COLUMN category_type ENUM('wood') NOT NULL DEFAULT 'wood'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE products MODIFY COLUMN category_type VARCHAR(50) DEFAULT NULL");
        DB::table('products')->update(['category_type' => 'embroidery']);
        DB::statement("ALTER TABLE products MODIFY COLUMN category_type ENUM('embroidery','print') NOT NULL DEFAULT 'embroidery'");
    }
};
