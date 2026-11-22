<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add composite indexes on (company_id, employee_code) across core HRMS tables.
     */
    public function up(): void
    {
        $tables = [
            'employees' => 'idx_employees_company_empcode',
            'main_salary_employees' => 'idx_mse_company_empcode',
            'attendances' => 'idx_att_company_empcode',
            'attendance_raw_logs' => 'idx_arl_company_empcode',
            'main_salary_employee_sanctions' => 'idx_mses_company_empcode',
            'main_salary_employee_absences' => 'idx_msea_company_empcode',
            'main_salary_employee_additions' => 'idx_mseadd_company_empcode',
            'main_salary_employee_discounts' => 'idx_msed_company_empcode',
            'main_salary_employee_rewards' => 'idx_mser_company_empcode',
            'main_salary_employee_allowances' => 'idx_msealw_company_empcode',
            'main_salary_employee_loans' => 'idx_msel_company_empcode',
            'main_salary_employee_permanent_loans' => 'idx_msepl_company_empcode',
            'main_salary_p_loans_installments' => 'idx_mspli_company_empcode',
        ];

        foreach ($tables as $tableName => $indexName) {
            if (Schema::hasTable($tableName) && Schema::hasColumns($tableName, ['company_id', 'employee_code'])) {
                $hasIndex = collect(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
                if (!$hasIndex) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->index(['company_id', 'employee_code'], $indexName);
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'employees' => 'idx_employees_company_empcode',
            'main_salary_employees' => 'idx_mse_company_empcode',
            'attendances' => 'idx_att_company_empcode',
            'attendance_raw_logs' => 'idx_arl_company_empcode',
            'main_salary_employee_sanctions' => 'idx_mses_company_empcode',
            'main_salary_employee_absences' => 'idx_msea_company_empcode',
            'main_salary_employee_additions' => 'idx_mseadd_company_empcode',
            'main_salary_employee_discounts' => 'idx_msed_company_empcode',
            'main_salary_employee_rewards' => 'idx_mser_company_empcode',
            'main_salary_employee_allowances' => 'idx_msealw_company_empcode',
            'main_salary_employee_loans' => 'idx_msel_company_empcode',
            'main_salary_employee_permanent_loans' => 'idx_msepl_company_empcode',
            'main_salary_p_loans_installments' => 'idx_mspli_company_empcode',
        ];

        foreach ($tables as $tableName => $indexName) {
            if (Schema::hasTable($tableName)) {
                $hasIndex = collect(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();
                if ($hasIndex) {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }
};

