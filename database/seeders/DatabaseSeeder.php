<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Super Admin
        User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'super@admin.com',
            'password' => bcrypt('12345678'),
            'role' => 'super_admin',
            'is_active' => 1,
        ]);

        // Create a Test Company
        $company = Company::create([
            'company_name' => 'شركة التجربة التقنية',
            'company_code' => 'TEST-01',
            'address' => 'الرياض، المملكة العربية السعودية',
            'is_active' => 1,
        ]);

        // Create Company Admin for the test company
        $adminUser = User::create([
            'name' => 'مدير الشركة',
            'username' => 'companyadmin',
            'email' => 'admin@company.com',
            'password' => bcrypt('12345678'),
            'role' => 'company_admin',
            'company_id' => $company->id,
            'is_active' => 1,
        ]);
        // Create default roles for company and assign Manager role to company admin
        $adminRole = Role::where('name', 'company_admin')->first();
        if ($adminRole) {
            $adminUser->assignRole($adminRole);
        }

        // Create Employee record for the Manager
        $employee = \App\Models\Employee::create([
            'employee_code' => 1,
            'emp_name' => $adminUser->name,
            'emp_email' => $adminUser->email,
            'company_id' => $company->id,
            'added_by' => $adminUser->id,
            'date' => date('Y-m-d'),
            'functional_status' => 1,
            'does_has_attendance' => 1,
            'sal_cash_or_visa' => 1,
        ]);

        // Link the Employee ID to the User
        $adminUser->update(['employee_id' => $employee->id]);

        // Create default settings for test company
        CompanySetting::create([
            'company_id' => $company->id,
            'after_miniute_calculate_delay' => 15,
            'after_miniute_calculate_early_departure' => 15,
            'after_miniute_quarterday' => 60,
            'after_time_half_daycut' => 3,
            'after_time_allday_daycut' => 5,
            'monthly_vacation_balance' => 2.5,
            'after_days_begin_vacation' => 90,
            'first_balance_begin_vacation' => 7.5,
            'sanctions_value_first_abcence' => 1,
            'sanctions_value_second_abcence' => 2,
            'sanctions_value_third_abcence' => 3,
            'sanctions_value_fourth_abcence' => 4,
        ]);

        $this->call([
            PermissionSeeder::class,
            DefaultRolesSeeder::class,
        ]);
    }
}
