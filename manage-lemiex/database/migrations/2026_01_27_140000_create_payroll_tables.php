<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Salary Tiers (Mức lương mẫu)
        Schema::create('salary_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Tier 15$"
            $table->decimal('hourly_rate', 10, 2); // 15.00
            $table->string('currency', 3)->default('USD');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Employee Salaries (Lịch sử lương nhân viên)
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('salary_tier_id')->nullable()->constrained('salary_tiers')->onDelete('set null');
            $table->decimal('custom_hourly_rate', 10, 2)->nullable(); // Nếu muốn override tier
            $table->date('effective_date'); // Ngày bắt đầu áp dụng
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Index để query nhanh lương tại thời điểm cụ thể
            $table->index(['employee_id', 'effective_date', 'is_active']);
        });

        // 3. Salary Adjustments (Thưởng/Phạt, KPI)
        Schema::create('salary_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('type'); // 'bonus', 'penalty', 'kpi', 'advance'
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustments');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_tiers');
    }
};
