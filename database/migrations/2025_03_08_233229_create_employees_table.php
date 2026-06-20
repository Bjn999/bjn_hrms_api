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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->integer('employee_code')->comment('كود الموظف التلقائي (لا يتغير)');
            $table->integer('zketo_code')->nullable()->comment('كود بصمةالموظف من جهاز البصمة (لا يتغير)');
            $table->string('emp_name', 300)->nullable()->comment('اسم الموظف');
            $table->tinyInteger('emp_gender')->nullable()->default(1)->comment('نوع الجنس: 1 ذكر - 2 أنثى');
            // $table->integer('branch_id')->comment('الفرع التابع له الموظف');
            $table->foreignId('branch_id')->nullable()->comment('الفرع التابع له الموظف')->references('id')->on('branches')->onUpdate('cascade');
            $table->foreignId('qualifications_id')->nullable()->comment('المؤهل التعليمي')->references('id')->on('qualifications')->onUpdate('cascade');
            $table->string('qualifications_year', 10)->nullable()->comment('سنة التخرج من المؤهل التعليمي');
            $table->tinyInteger('graduation_estimate')->nullable()->comment('تقدير سنة التخرج: 1 مقبول - 2 جيد - 3 جيد جداً - 4 ممتاز');
            $table->string('graduation_specialization', 255)->nullable()->comment('تخصص التخرج من المؤهل التعليمي');
            $table->string('emp_email', 100)->nullable()->comment('إيميل الموظف');
            $table->date('emp_birth_date')->nullable()->comment('تاريخ ميلاد الموظف');
            $table->string('emp_national_identity', 50)->nullable()->comment('رقم الهوية الوطنية / البطاقة الشخصية للموظف');
            $table->date('emp_end_identity_date')->nullable()->comment('تاريخ انتهاء بطاقة/هوية الموظف');
            $table->string('emp_identity_place', 225)->nullable()->comment('مكان اصدار الهوية الشخصية للموظف');
            $table->integer('blood_group_id')->nullable()->comment('حقل فصيلة الدم');
            $table->foreignId('emp_nationality_id')->nullable()->comment('جنسية الموظف/ـة')->references('id')->on('nationalities')->onUpdate('cascade');
            $table->foreignId('emp_lang_id')->nullable()->comment('اللغة التي يتكلم بها الموظف')->references('id')->on('languages')->onUpdate('cascade');
            $table->integer('emp_social_status_id')->nullable()->comment('الحالة الإجتماعية');
            $table->integer('children_number')->nullable()->default(0);
            $table->foreignId('religion_id')->nullable()->comment('حقل الديانة')->references('id')->on('religions')->onUpdate('cascade');
            $table->integer('country_id')->nullable()->comment('دولة الموظف');
            $table->integer('governorate_id')->nullable()->comment('محافظة الموظف');
            $table->integer('city_id')->nullable()->comment('مدينة الموظف');
            $table->string('staies_address', 300)->nullable()->comment('عنوان الاقامة الفعلي للموظف');
            $table->string('emp_home_tel', 50)->nullable()->comment('رقم هاتف المنزل');
            $table->string('emp_work_tel', 50)->nullable()->comment('رقم هاتف العمل');
            $table->integer('emp_military_id')->nullable()->comment('الحالة العسكرية');
            $table->date('emp_military_date_from')->nullable()->comment('تاريخ بداية الخدمة العسكرية');
            $table->date('emp_military_date_to')->nullable()->comment('تاريخ نهاية الخدمة العسكرية');
            $table->string('emp_military_weapon')->nullable()->comment('نوع السلاح في الخدمة العسكرية');
            $table->date('exemption_date')->nullable()->comment('تاريخ الإعفاء من الخدمة العسكرية');
            $table->string('exemption_reason', 300)->nullable()->comment('سبب الإعفاء من الخدمة العسكرية');
            $table->string('postponement_reason', 300)->nullable()->comment('سبب تأجل الخدمة العسكرية');
            $table->tinyInteger('does_has_driving_license')->nullable()->default(0)->comment('هل يمتلك رخصة قيادة');
            $table->string('driving_license_num', 50)->nullable()->comment('رقم رخصة قيادة');
            $table->integer('driving_license_type_id')->nullable()->comment('نوع رخصة قيادة');
            $table->tinyInteger('has_relatives')->nullable()->default(0)->comment('هل له أقارب بالعلم: 0 لا يوجد - 1 يوجد');
            $table->string('relatives_details', 600)->nullable()->comment('تفاصيل الأقارب بالعمل');
            $table->tinyInteger('is_disabilities_processes')->nullable()->default(0)->comment('هل له إعاقة: 0 لا يوجد - 1 يوجد');
            $table->string('disabilities_processes', 500)->nullable()->comment('نوع الإعاقة');
            $table->string('notes', 500)->nullable()->comment('ملاحظات على الموظف');

            $table->date('emp_start_date')->nullable()->comment('تاريخ بدأ العمل للموظف');
            $table->tinyInteger('functional_status')->nullable()->default(0)->comment('حالة الموظف: 0 لا يعمل - 1 يعمل');
            $table->foreignId('emp_departments_id')->nullable()->comment('الإدارة المنتمي لها الموظف')->references('id')->on('departments')->onUpdate('cascade');
            $table->foreignId('emp_job_id')->nullable()->references('id')->on('jobs_categories')->onUpdate('cascade');
            $table->tinyInteger('does_has_attendance')->nullable()->default(1)->comment('هل الموظف ملزم بعمل بصمة الحضور والإنصراف');
            $table->tinyInteger('is_has_fixed_shift')->nullable()->comment('هل للموظف شفت ثابت');
            $table->foreignId('shift_type_id')->nullable()->comment('نوع الشفت الثابت أن وجد')->references('id')->on('shifts_types')->onUpdate('cascade');
            $table->decimal('daily_work_hour', 10, 2)->nullable()->comment('عدد ساعات الموظف في حالة ليس له شفت ثابت');
            $table->decimal('emp_sal', 10, 2)->nullable()->default(0)->comment('راتب الموظف');
            $table->decimal('day_price', 10, 2)->nullable()->comment('سعر يوم الموظف');
            $table->tinyInteger('sal_cash_or_visa')->nullable()->default(1)->comment('نوع صرف الراتب: 1 كاش - 2 فيزا/بنك');
            $table->string('bank_number_account', 50)->nullable()->comment('رقم حساب البنك للموظف إن وجد');
            $table->tinyInteger('motivation_type')->nullable()->default(0)->comment('الحافز: 0 لا يوجد - 1 ثابت - 2 متغير');
            $table->decimal('motivation', 10, 2)->nullable()->default(0)->comment('قيمة الحافز الثابت إن وجد');
            $table->tinyInteger('is_social_insurance')->nullable()->default(0)->comment('هل للموظف تأمين إجتماعي');
            $table->decimal('social_insurance_cut_monthly', 10, 2)->nullable()->comment('قيمة استقطاع التأمين الإجتماعي الشهري للموظف');
            $table->string('social_insurance_number', 50)->nullable()->comment('رقم التأمين الإجتماعي للموظف');
            $table->tinyInteger('is_medical_insurance')->nullable()->default(0)->comment('هل للموظف تأمين الطبي');
            $table->decimal('medical_insurance_cut_monthly', 10, 2)->nullable()->comment('قيمة استقطاع التأمين الطبي الشهري للموظف');
            $table->string('medical_insurance_number', 50)->nullable()->comment('رقم التأمين الطبي للموظف');
            $table->tinyInteger('is_active_for_vaccation')->nullable()->default(0)->comment('هل الموظف ينزل له رصيد إجازات');
            $table->string('urgent_person_details', 600)->nullable()->comment('تفاصيل شخص يمكن الرجوع إليه للوصول للموظف');
            
            $table->string('emp_cafel')->nullable()->comment('اسم الكفيل إن وجد');
            $table->string('emp_pasport_no', 100)->nullable()->comment('رقم الجواز إن وجد');
            $table->string('emp_pasport_place', 100)->nullable()->comment('مكان استخراج الجواز إن وجد');
            $table->date('emp_passport_exp')->nullable()->comment('تاريخ انتهاء جواز الموظف');
            $table->string('home_address', 300)->nullable()->comment('عنوان إقامة الموظف في بلده الأم');
            $table->foreignId('resignation_id')->nullable()->comment('نوع ترك العمل')->references('id')->on('resignations')->onUpdate('cascade');
            $table->date('resignation_date')->nullable()->comment('تاريخ ترك العمل للموظف');
            $table->string('resignation_cause', 255)->nullable()->comment('سبب ترك الموظف للعمل');
            $table->string('emp_photo', 150)->nullable()->comment('صورة الموظف');
            $table->string('emp_cv', 150)->nullable()->comment('السيرة الذاتية للموظف');
            $table->date('date')->nullable()->comment('تاريخ إضافة الموظف');
            $table->tinyInteger('does_has_fixed_allowance')->nullable()->default(0)->comment('هل للموظف بدلات ثابتة');
            $table->tinyInteger('is_done_vaccation_formula')->nullable()->default(0)->comment('هل تمت المعادلة التلقائية لإحتساب الرصيد السنوي من الإجازات للموظف');
            $table->tinyInteger('is_sensitive_manager_data')->nullable()->default(0)->comment('هل البيانات حساسة (للإدارة مثلاً) ولا تظهر إلا لصلاحيات خاصة');
            
            $table->integer('com_code');
            $table->foreignId('added_by')->references('id')->on('admins')->onUpdate('cascade');
            $table->foreignId('updated_by')->nullable()->references('id')->on('admins')->onUpdate('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
