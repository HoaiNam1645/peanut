<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_net_salaries', function (Blueprint $table) {
            $table->decimal('company_tax', 15, 2)->default(0)->after('net_salary');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_net_salaries', function (Blueprint $table) {
            $table->dropColumn('company_tax');
        });
    }
};
