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
        Schema::create('main_salary_p_loans_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('main_salary_p_loans_id')->comment('السلفة الدائمة التابع له هذا القسط')->references('id')->on('main_salary_employee_permanent_loans')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('main_salary_employee_id')->nullable()->comment('سجل راتب الموظف التابع له هذا القسط')->references('id')->on('main_salary_employees')->onUpdate('cascade');
            $table->bigInteger('employee_code');
            $table->decimal('monthly_installment_value', 10, 2)->comment('قيمة القسط الشهري');
            $table->string('year_and_month', 10)->comment('تاريخ الاستحقاق');
            $table->integer('status')->default(0)->comment('حالة الدفع: 0 لم يتم يدفع ، 1 تم الدفع على الراتب - 2 تم الدفع كاش');
            
            $table->integer('is_parent_dismissal')->default(0)->comment('هل تم صرف الاب');
            $table->integer('is_archived')->default(0)->comment('هل تم الارشفة');
            $table->foreignId('archived_by')->nullable()->comment('من الذي ارشفه')->references('id')->on('admins')->onUpdate('cascade');
            $table->dateTime('archived_at')->nullable()->comment('تاريخ الارشفة');
            $table->string('notes', 100)->nullable();
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
        Schema::dropIfExists('main_salary_p_loans_installments');
    }
};
