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
        // 1. جدول سجلات الحضور والانصراف الرئيسية المجهزة للبصمة والاحتساب اليومي
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // بيانات الربط والتعريف
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->integer('employee_code')->comment('كود الموظف بالنظام');
            
            // بيانات الفترات المالية والشيفتات
            $table->foreignId('finance_months_periods_id')->comment('كود الشهر المالي')->constrained('finance_months_periods')->onUpdate('cascade');
            $table->foreignId('main_salary_employee_id')->nullable()->comment('كود سجل الراتب للشهر المالي')->constrained('main_salary_employees')->onUpdate('cascade')->onDelete('set null');
            $table->date('attendance_date')->comment('تاريخ يوم الحضور والإنصراف');
            
            $table->string('variables', 250)->nullable()->comment('المتغيرات');
            $table->decimal('shift_hours', 5, 2)->default(0)->comment('عدد ساعات العمل المتعاقد عليها');
            $table->tinyInteger('attendance_status')->default(1)->comment('حالة اليوم: 1=حضور كامل، 2=غياب، 3=تأخير، 4=انصراف مبكر، 5=إجازة، إلخ');
            
            // أوقات الحضور والإنصراف الفعلي من البصمة
            $table->decimal('total_hours', 5, 2)->default(0)->comment('إجمالي ساعات العمل الفعلي المنجزة');
            $table->dateTime('check_in')->nullable()->comment('تاريخ ووقت بصمة الحضور الفعلي');
            $table->dateTime('check_out')->nullable()->comment('تاريخ ووقت بصمة الإنصراف الفعلي');
            $table->tinyInteger('late_attendance')->default(0)->comment('هل التأخير عن بداية الشفت');
            $table->tinyInteger('early_leave')->default(0)->comment('هل الإنصراف المبكر قبل نهاية الشفت');

            $table->decimal('overtime_hours', 5, 2)->default(0)->comment('ساعات العمل الإضافي المحتسبة');
            $table->decimal('absence_hours', 5, 2)->default(0)->comment('ساعات الغياب في العمل');
            $table->string('ezen_hours', 250)->nullable()->comment('تفاصيل الإذن إن وجد');
            $table->tinyInteger('is_processed')->default(0)->comment('هل تم اخذ اجراء على الموظف');
            $table->integer('vacations_type_id')->nullable()->default(0)->comment('كود الإجازة');
            $table->integer('occasions_id')->nullable()->default(0)->comment('نوع الإجازة الرسمية ان وجدت');
            $table->tinyInteger('cut')->default(0)->comment('0=nothing, 0.25=quarter day, 0.5=half day, 1=one day');
            $table->string('year_and_month', 10)->nullable()->default(0)->comment('السنة والشهر');
            $table->integer('branch_id')->nullable()->comment('فرع الموظف لحظة الراتب');
            $table->integer('functional_status')->nullable()->default(0)->comment('الحالة الوظيفية للموظف لحظة الراتب');
            $table->dateTime('actionDateTime')->nullable()->comment('وقت وتاريخ البصمة بالتفصيل');

            // الاعتماد والأرشفة
            $table->tinyInteger('is_archived')->nullable()->default(0)->comment('هل تم ترحيل والأرشفة: 0=لا, 1=نعم');
            $table->dateTime('archived_at')->nullable()->comment('تمت الأرشفة في');
            $table->foreignId('archived_by')->nullable()->comment('تمت الأرشفة بواسطة')->constrained('users')->onUpdate('cascade')->onDelete('set null');
            
            // تتبع المستخدمين
            $table->foreignId('added_by')->comment('تمت الإضافة بواسطة')->constrained('users')->onUpdate('cascade');
            $table->foreignId('updated_by')->nullable()->comment('تم التعديل بواسطة')->constrained('users')->onUpdate('cascade');

            $table->timestamps();

            // الفهارس لتسريع الاستعلامات والبحث وحساب الرواتب
            $table->index(['company_id', 'attendance_date']);
            $table->index(['employee_code', 'attendance_date']);
            $table->index(['finance_months_periods_id']);
            $table->index(['company_id', 'created_at'], 'comp_created_idx');
            $table->index(['company_id', 'employee_code'], 'comp_emp_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
