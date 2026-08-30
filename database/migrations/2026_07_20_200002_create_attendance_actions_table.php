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
        Schema::create('attendance_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('finance_months_periods_id')->comment('الشهر المالي')->constrained('finance_months_periods')->onUpdate('cascade');
            $table->foreignId('attendance_id')->comment('كود الجدول الرئيسي لسجلات البصمة')->constrained('attendances')->onUpdate('cascade')->onDelete('cascade');
            $table->integer('employee_code')->comment('كود الموظف بالنظام');
            
            $table->dateTime('actionDateTime')->nullable()->comment('وقت وتاريخ البصمة');
            $table->tinyInteger('actionType')->comment('نوع البصمة');
            $table->tinyInteger('actionMethod')->comment('1=dynamic by pasma, 2= manual');
            $table->tinyInteger('it_is_active_with_parent')->default(0)->comment('هل هي المستعملة بتقفيل الأب');
            $table->tinyInteger('is_processed')->default(0)->comment('هل تم اخذ اجراء على الموظف');
            
            $table->foreignId('added_by')->comment('تمت الإضافة بواسطة')->constrained('users')->onUpdate('cascade');
            $table->foreignId('updated_by')->nullable()->comment('تم التعديل بواسطة')->constrained('users')->onUpdate('cascade');
            
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();

            $table->timestamps();

            // فهارس لتسريع استعراض حركات البصمة التفصيلية لموظف معين
            $table->index(['attendance_id']);
            $table->index(['company_id', 'employee_code', 'actionDateTime'], 'actions_search_index');
            $table->index(['company_id', 'created_at'], 'comp_created_idx');
            $table->index(['company_id', 'employee_code'], 'comp_emp_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_actions');
    }
};
