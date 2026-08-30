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
        Schema::create('main_salary_employee_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_salary_employee_id')->references('id')->on('main_salary_employees')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('finance_month_periods_id')->references('id')->on('finance_months_periods')->onUpdate('cascade')->onDelete('cascade');
            $table->bigInteger('employee_code');
            $table->decimal('day_price', 10, 2)->comment('الراتب اليومي للموظف');
            $table->decimal('value', 10, 2)->comment('كم يوم غياب');
            $table->decimal('total', 10, 2)->comment('اجمالي الغياب');
            $table->integer('is_archived')->default(0)->comment('حالة الارشفة');
            $table->foreignId('archived_by')->nullable()->comment('من الذي ارشفه')->references('id')->on('users')->onUpdate('cascade');
            $table->dateTime('archived_at')->nullable()->comment('تاريخ الارشفة');
            $table->integer('is_auto')->default(0)->comment('هل يتم تلقائي من النظام ام بشكل يدوي');
            $table->string('notes', 100)->nullable();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->integer('active')->default(1);
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
        Schema::dropIfExists('main_salary_employee_absences');
    }
};
