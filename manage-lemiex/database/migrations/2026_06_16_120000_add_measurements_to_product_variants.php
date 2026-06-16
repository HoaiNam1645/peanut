<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garment size-chart measurements (inch + cm) per variant.
     * Distinct from weight/length/width/height which are package dimensions.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('chest_inch', 8, 2)->nullable()->after('height');
            $table->decimal('chest_cm', 8, 2)->nullable()->after('chest_inch');
            $table->decimal('length_inch', 8, 2)->nullable()->after('chest_cm');
            $table->decimal('length_cm', 8, 2)->nullable()->after('length_inch');
            $table->decimal('neck_inch', 8, 2)->nullable()->after('length_cm');
            $table->decimal('neck_cm', 8, 2)->nullable()->after('neck_inch');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn([
                'chest_inch', 'chest_cm',
                'length_inch', 'length_cm',
                'neck_inch', 'neck_cm',
            ]);
        });
    }
};
