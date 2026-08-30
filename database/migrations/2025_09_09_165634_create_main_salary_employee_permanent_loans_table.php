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
        Schema::create('main_salary_employee_permanent_loans', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('employee_code');
            $table->decimal('emp_sal', 10, 2)->comment('راتب للموظف');
            $table->decimal('total', 10, 2)->comment('اجمالي السلفة');
            $table->decimal('monthly_installment_value', 10, 2)->comment('قيمة القسط الشهري');
            $table->integer('months_number')->comment('عدد الشهور للأقساط');
            $table->string('year_and_month_start', 10)->comment('بداية السداد');
            $table->date('year_and_month_start_date')->comment('تاريخ بداية السداد');
            $table->decimal('total_paid', 10, 2)->default(0)->comment('اجمالي المبلغ المدفوع من السلفة');
            $table->decimal('total_remain', 10, 2)->default(0)->comment('اجمالي المبلغ المتبقي من السلفة');
            $table->integer('is_disbursed')->default(0)->comment('هل تم الصرف');
            $table->foreignId('disbursed_by')->nullable()->comment('من الذي صرف')->references('id')->on('users')->onUpdate('cascade');
            $table->dateTime('disbursed_at')->nullable()->comment('تاريخ الصرف');

            $table->integer('is_archived')->default(0)->comment('هل تم الارشفة');
            $table->foreignId('archived_by')->nullable()->comment('من الذي ارشفه')->references('id')->on('users')->onUpdate('cascade');
            $table->dateTime('archived_at')->nullable()->comment('تاريخ الارشفة');
            $table->string('notes', 100)->nullable();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('added_by')->references('id')->on('users')->onUpdate('cascade');
            $table->foreignId('updated_by')->nullable()->references('id')->on('users')->onUpdate('cascade');

            $table->timestamps();
            $table->index(['company_id', 'created_at'], 'comp_created_idx');
            $table->index(['company_id', 'employee_code'], 'comp_emp_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_salary_employee_permanent_loans');
    }
};
