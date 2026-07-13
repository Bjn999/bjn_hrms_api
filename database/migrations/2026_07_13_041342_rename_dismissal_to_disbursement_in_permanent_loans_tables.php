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
        Schema::table('main_salary_employee_permanent_loans', function (Blueprint $table) {
            $table->renameColumn('is_dismissal', 'is_disbursed');
            $table->renameColumn('dismissal_by', 'disbursed_by');
            $table->renameColumn('dismissal_at', 'disbursed_at');
        });

        Schema::table('main_salary_p_loans_installments', function (Blueprint $table) {
            $table->renameColumn('is_parent_dismissal', 'is_parent_disbursed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('main_salary_employee_permanent_loans', function (Blueprint $table) {
            $table->renameColumn('is_disbursed', 'is_dismissal');
            $table->renameColumn('disbursed_by', 'dismissal_by');
            $table->renameColumn('disbursed_at', 'dismissal_at');
        });

        Schema::table('main_salary_p_loans_installments', function (Blueprint $table) {
            $table->renameColumn('is_parent_disbursed', 'is_parent_dismissal');
        });
    }
};
