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
        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('vacation_type_id')->constrained('vacation_types')->cascadeOnDelete();
            
            $table->date('from_date')->comment('تاريخ بدء الإجازة');
            $table->date('to_date')->comment('تاريخ نهاية الإجازة');
            $table->decimal('days_count', 8, 2)->comment('عدد أيام الإجازة المطلوبة');
            $table->text('reason')->nullable()->comment('سبب الإجازة');
            $table->string('attachment_path', 500)->nullable()->comment('ملف مرفق (تقرير طبي أو مبرر)');
            
            $table->tinyInteger('status')->default(0)->comment('0=قيد المراجعة Pending، 1=معتمد Approved، 2=مرفوض Rejected، 3=ملغي Cancelled');
            $table->unsignedBigInteger('action_by')->nullable()->comment('معرف المستخدم الذي اعتمد أو رفض');
            $table->text('action_reason')->nullable()->comment('سبب الرفض أو ملاحظات الإدارة');
            $table->timestamp('action_at')->nullable()->comment('وقت اتخاذ الإجراء');
            
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employee_id'], 'comp_emp_vac_req_idx');
            $table->index(['company_id', 'status'], 'comp_status_vac_req_idx');
            $table->index(['company_id', 'from_date', 'to_date'], 'comp_dates_vac_req_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_requests');
    }
};
