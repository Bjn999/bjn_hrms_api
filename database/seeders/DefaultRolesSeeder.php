<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DefaultRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'company_admin',
                'display_name' => 'مدير الشركة',
                'guard_name' => 'web'
            ],
            [
                'name' => 'hr_manager',
                'display_name' => 'مدير الموارد البشرية',
                'guard_name' => 'web'
            ],
            [
                'name' => 'accountant',
                'display_name' => 'محاسب',
                'guard_name' => 'web'
            ],
            [
                'name' => 'supervisor',
                'display_name' => 'مشرف',
                'guard_name' => 'web'
            ],
            [
                'name' => 'employee',
                'display_name' => 'موظف',
                'guard_name' => 'web'
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                [
                    'name' => $roleData['name'], 
                    'guard_name' => $roleData['guard_name']
                ],
                [
                    'display_name' => $roleData['display_name']
                ]
            );
            
            if ($roleData['name'] === 'company_admin') {
                $role->syncPermissions(Permission::all());
            } elseif ($roleData['name'] === 'hr_manager') {
                // HR Manager: Full operations across HR, employees, attendance, and financials
                $role->syncPermissions(Permission::whereNotIn('name', [
                    'view_settings',
                    'edit_settings',
                    'view_roles',
                    'create_roles',
                    'edit_roles',
                    'delete_roles'
                ])->get());
            } elseif ($roleData['name'] === 'accountant') {
                // Accountant: Payroll, financial records, loans, allowances, bonuses, deductions
                $accountantPermissions = [
                    'view_dashboard',
                    'view_company_dashboard',
                    'view_own_salary',
                    // Salaries
                    'view_salaries',
                    'create_salaries',
                    'edit_salaries',
                    'archive_salaries',
                    'view_salary_slips',
                    // Loans & Permanent Loans
                    'view_loans',
                    'create_loans',
                    'edit_loans',
                    'delete_loans',
                    'view_permanent_loans',
                    'create_permanent_loans',
                    'edit_permanent_loans',
                    'disburse_permanent_loans',
                    'pay_permanent_loans',
                    // Financial Adjustments
                    'view_rewards',
                    'create_rewards',
                    'edit_rewards',
                    'delete_rewards',
                    'view_discounts',
                    'create_discounts',
                    'edit_discounts',
                    'delete_discounts',
                    'view_additions',
                    'create_additions',
                    'edit_additions',
                    'delete_additions',
                    'view_allowances',
                    'create_allowances',
                    'edit_allowances',
                    'delete_allowances',
                    'view_sanctions',
                    'view_absences',
                    // Catalogs & Lookups (Financial)
                    'view_reward_types',
                    'create_reward_types',
                    'edit_reward_types',
                    'view_discount_types',
                    'create_discount_types',
                    'edit_discount_types',
                    'view_allowance_types',
                    'create_allowance_types',
                    'edit_allowance_types',
                    'view_finance_calendars',
                    // Lookups (Read-only for payroll linking)
                    'view_employees',
                    'view_branches',
                    'view_departments'
                ];
                $role->syncPermissions(Permission::whereIn('name', $accountantPermissions)->get());
            } elseif ($roleData['name'] === 'supervisor') {
                // Supervisor: Team management, attendance tracking, and reporting requests
                $supervisorPermissions = [
                    'view_dashboard',
                    'view_own_salary',
                    'view_employees',
                    'view_branches',
                    'view_departments',
                    'view_shifts',
                    // Attendance
                    'view_attendances',
                    'sync_biotime',
                    'create_attendances',
                    'edit_attendances',
                    // Operations
                    'view_sanctions',
                    'create_sanctions',
                    'view_absences',
                    'create_absences',
                    'view_additions',
                    'create_additions',
                    'view_rewards',
                    'create_rewards'
                ];
                $role->syncPermissions(Permission::whereIn('name', $supervisorPermissions)->get());
            } elseif ($roleData['name'] === 'employee') {
                // Standard Employee: Personal dashboard & viewing own salary slip
                $role->syncPermissions(['view_dashboard', 'view_own_salary']);
            }
        }
    }
}
