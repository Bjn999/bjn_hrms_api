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
        Schema::create('employee_vacation_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->integer('year')->comment('السنة المالية أو الميلادية');
            
            $table->decimal('carried_over_balance', 8, 2)->default(0)->comment('الرصيد المرحل من سنوات سابقة');
            $table->decimal('initial_assigned_balance', 8, 2)->default(0)->comment('الرصيد الأولي عند التعيين');
            $table->decimal('monthly_accrued_balance', 8, 2)->default(0)->comment('الرصيد المكتسب شهرياً خلال السنة');
            $table->decimal('total_entitled_balance', 8, 2)->default(0)->comment('إجمالي الرصيد المستحق الكلي');
            $table->decimal('used_balance', 8, 2)->default(0)->comment('الرصيد المستهلك / المأخوذ');
            $table->decimal('pending_balance', 8, 2)->default(0)->comment('الرصيد المعلق تحت المراجعة');
            $table->decimal('remaining_balance', 8, 2)->default(0)->comment('الرصيد المتبقي الفعلي المتاح');
            
            $table->text('notes')->nullable()->comment('ملاحظات / تسويات على الرصيد');
            $table->timestamp('last_calculated_at')->nullable()->comment('تاريخ آخر احتساب تلقائي');
            
            $table->unsignedBigInteger('added_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'year'], 'comp_emp_year_unique');
            $table->index(['company_id', 'employee_id'], 'comp_emp_vac_bal_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_vacation_balances');
    }
};
