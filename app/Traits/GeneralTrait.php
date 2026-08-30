<?php

namespace App\Traits;

use App\Models\Employee;
use App\Models\Employee_fixed_allowance;
use App\Models\Finance_months_periods;
use App\Models\Main_salary_employee;
use App\Models\Main_salary_employee_sanction;
use App\Models\Main_salary_employee_addition;
use App\Models\Main_salary_employee_absence;
use App\Models\Main_salary_employee_discount;
use App\Models\Main_salary_employee_reward;
use App\Models\Main_salary_employee_allowance;
use App\Models\Main_salary_employee_loan;
use App\Models\Main_salary_employee_permanent_loan;
use App\Models\Main_salary_p_loans_installment;

trait GeneralTrait
{
    public function recaculate_main_salary_employee($main_salary_employee_Id)
    {
        $company_id = auth()->user()->company_id;
        $main_salary_employee_data = Main_salary_employee::where([
            'company_id' => $company_id, 
            'id' => $main_salary_employee_Id, 
            'is_archived' => 0
        ])->first();
        
        if ($main_salary_employee_data) {
            $employeeData = Employee::where([
                'company_id' => $company_id, 
                'employee_code' => $main_salary_employee_data->employee_code
            ])->first();
            
            $finance_month_Data = Finance_months_periods::where([
                'company_id' => $company_id, 
                'id' => $main_salary_employee_data->finance_month_id
            ])->first();

            if ($employeeData && $finance_month_Data) {
                $emp_sal = (float) $employeeData->emp_sal;
                $day_price = (float) $employeeData->day_price;
                $motivation = (float) $employeeData->motivation;
                
                $fixed_allowances = (float) Employee_fixed_allowance::where([
                    'company_id' => $company_id, 
                    'employee_id' => $employeeData->id
                ])->sum('value');
                
                $changable_allowances = (float) Main_salary_employee_allowance::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('total');
                
                $reward = (float) Main_salary_employee_reward::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('total');
                
                $additional_days_counter = (float) Main_salary_employee_addition::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('value');
                
                $additional_days_total = (float) Main_salary_employee_addition::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('total');

                // Benefits
                $total_benefits = $emp_sal + $motivation + $fixed_allowances + $changable_allowances + $reward + $additional_days_total;

                // Deductions
                $socialinsurancecutmonthly = (float) $employeeData->social_insurance_cut_monthly;
                $medicalinsurancecutmonthly = (float) $employeeData->medical_insurance_cut_monthly;
                
                $sanctions_days_counter = (float) Main_salary_employee_sanction::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('value');
                
                $sanctions_days_total = (float) Main_salary_employee_sanction::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('total');
                
                $absence_days_counter = (float) Main_salary_employee_absence::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('value');
                
                $absence_days_total = (float) Main_salary_employee_absence::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('total');
                
                $discount = (float) Main_salary_employee_discount::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('total');
                
                $monthly_loan = (float) Main_salary_employee_loan::where([
                    'company_id' => $company_id, 
                    'main_salary_employee_id' => $main_salary_employee_Id
                ])->sum('total');
                
                $permanent_loan = (float) Main_salary_p_loans_installment::where([
                    'employee_code' => $main_salary_employee_data->employee_code,
                    'company_id' => $company_id,
                    'year_and_month' => $finance_month_Data->year_and_month,
                    'is_archived' => 0,
                    'is_parent_disbursed' => 1,
                ])->where('status', '!=', 2)->sum('monthly_installment_value');

                // Update permanent installments status to 1
                Main_salary_p_loans_installment::where([
                    'employee_code' => $main_salary_employee_data->employee_code,
                    'company_id' => $company_id,
                    'year_and_month' => $finance_month_Data->year_and_month,
                    'is_archived' => 0,
                    'is_parent_disbursed' => 1,
                ])->where('status', '!=', 2)->update([
                    'status' => 1,
                    'main_salary_employee_id' => $main_salary_employee_Id
                ]);

                // Total Deduction
                $total_deduction = $socialinsurancecutmonthly + $medicalinsurancecutmonthly + $sanctions_days_total + $absence_days_total + $discount + $monthly_loan + $permanent_loan;

                // Net Salary
                $final_the_net = $main_salary_employee_data->last_salary_remain_balance + ($total_benefits - $total_deduction);

                // Update employee salary record
                $main_salary_employee_data->update([
                    'emp_sal' => $emp_sal,
                    'day_price' => $day_price,
                    'motivation' => $motivation,
                    'fixed_allowances' => $fixed_allowances,
                    'changable_allowances' => $changable_allowances,
                    'reward' => $reward,
                    'additional_days_counter' => $additional_days_counter,
                    'additional_days_total' => $additional_days_total,
                    'total_benefits' => $total_benefits,
                    
                    'socialinsurancecutmonthly' => $socialinsurancecutmonthly,
                    'medicalinsurancecutmonthly' => $medicalinsurancecutmonthly,
                    'sanctions_days_counter' => $sanctions_days_counter,
                    'sanctions_days_total' => $sanctions_days_total,
                    'absence_days_counter' => $absence_days_counter,
                    'absence_days_total' => $absence_days_total,
                    'discount' => $discount,
                    'monthly_loan' => $monthly_loan,
                    'permanent_loan' => $permanent_loan,
                    'total_deduction' => $total_deduction,
                    
                    'final_the_net' => $final_the_net,
                    'sal_cash_or_visa' => $employeeData->sal_cash_or_visa,
                    'branch_id' => $employeeData->branch_id,
                    'emp_departments_id' => $employeeData->emp_departments_id,
                    'emp_job_id' => $employeeData->emp_job_id,
                    'functional_status' => $employeeData->functional_status,
                    'year_and_month' => $finance_month_Data->year_and_month,
                    'finance_yr' => $finance_month_Data->finance_yr,
                ]);

                $p_installments = Main_salary_p_loans_installment::where([
                    'employee_code' => $main_salary_employee_data->employee_code,
                    'company_id' => $company_id,
                    'year_and_month' => $finance_month_Data->year_and_month,
                    'is_archived' => 0,
                    'is_parent_disbursed' => 1,
                ])->where('status', '!=', 2)->get();

                foreach ($p_installments as $p_inst) {
                    $p_installment_parent = Main_salary_employee_permanent_loan::where([
                        'id' => $p_inst->main_salary_p_loans_id,
                        'employee_code' => $main_salary_employee_data->employee_code,
                        'company_id' => $company_id,
                    ])->first();
                    if ($p_installment_parent) {
                        $p_installment_parent->update([
                            'total_paid' => $p_installment_parent->total_paid + $p_inst->monthly_installment_value,
                            'total_remain' => $p_installment_parent->total_remain - $p_inst->monthly_installment_value,
                        ]);
                    }
                }
            }
        }
    }
}
