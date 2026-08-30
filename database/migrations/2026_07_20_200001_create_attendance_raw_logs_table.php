<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_raw_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_months_periods_id')->comment('الشهر المالي')->constrained('finance_months_periods')->onUpdate('cascade');
            $table->bigInteger('employee_code')->nullable()->comment('كود الموظف بالنظام إن وجد');
            $table->dateTime('actionDateTime')->comment('وقت وتاريخ البصمة بالتفصيل');
            $table->tinyInteger('actionType')->comment('نوع البصمة');
            $table->tinyInteger('actionMethod')->default(2)->comment('1=dynamic by pasma, 2= manual');
            $table->foreignId('main_salary_employee_id')->nullable()->comment('سجل الراتب إن وجد')->constrained('main_salary_employees')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('added_by')->constrained('users')->onUpdate('cascade')->comment('تمت الإضافة بواسطة');
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();

            $table->timestamps();

            // فهارس لتسريع عملية فحص ومعالجة السجلات الخام
            $table->index(['company_id', 'employee_code', 'actionDateTime'], 'raw_logs_search_index');
            $table->index(['company_id', 'created_at'], 'comp_created_idx');
            $table->index(['company_id', 'employee_code'], 'comp_emp_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_raw_logs');
    }
};
