<?php

namespace App\Console\Commands;

use App\Models\Branche;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Jobs_categories;
use App\Models\Plan;
use App\Models\shifts_type;
use App\Models\Subscription;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SeedCompanies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:seed-companies 
                            {--count=5 : Number of test companies to generate} 
                            {--plan= : Specific plan slug (free, basic, premium, pro) or random}
                            {--with-employees=0 : Number of test employees to seed for each company}
                            {--include-expired : Generate a realistic mix of active, expiring soon, and expired subscriptions}
                            {--cleanup : Remove all previously seeded test companies and their data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed multiple test companies in bulk with admin accounts, subscriptions, settings, and optional employees.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        $planSlug = $this->option('plan');
        $employeesPerCompany = (int) $this->option('with-employees');
        $includeExpired = (bool) $this->option('include-expired');
        $cleanup = (bool) $this->option('cleanup');

        // 1. Cleanup Mode
        if ($cleanup) {
            $this->warn("Searching for test companies tagged with '[TEST-SEED]'...");
            $testCompanies = Company::where('domain', 'like', '%test-seed%')
                ->orWhere('company_name', 'like', '%[تجريبي]%')
                ->orWhere('company_name', 'like', '%Test Seed%')
                ->get();

            $companyCount = $testCompanies->count();
            if ($companyCount === 0) {
                $this->info('No test companies found to clean up.');
                return Command::SUCCESS;
            }

            $companyIds = $testCompanies->pluck('id')->toArray();

            DB::beginTransaction();
            try {
                // 1. Break circular foreign keys between User & Employee
                User::withoutGlobalScopes()->whereIn('company_id', $companyIds)->update(['employee_id' => null]);
                
                // 2. Delete Subscriptions
                Subscription::whereIn('company_id', $companyIds)->delete();

                // 3. Delete Lookups & Employees
                Employee::withoutGlobalScopes()->whereIn('company_id', $companyIds)->delete();
                Branche::withoutGlobalScopes()->whereIn('company_id', $companyIds)->delete();
                Department::withoutGlobalScopes()->whereIn('company_id', $companyIds)->delete();
                shifts_type::withoutGlobalScopes()->whereIn('company_id', $companyIds)->delete();
                Jobs_categories::withoutGlobalScopes()->whereIn('company_id', $companyIds)->delete();

                // 4. Delete Users
                User::withoutGlobalScopes()->whereIn('company_id', $companyIds)->delete();

                // 5. Delete Settings & Company
                CompanySetting::whereIn('company_id', $companyIds)->delete();
                Company::whereIn('id', $companyIds)->delete();

                DB::commit();
                $this->info("Successfully deleted {$companyCount} test companies and all associated data.");
                return Command::SUCCESS;
            } catch (Exception $e) {
                DB::rollBack();
                $this->error("Cleanup failed: {$e->getMessage()}");
                return Command::FAILURE;
            }
        }

        // 2. Fetch available plans
        $plans = Plan::where('is_active', true)->get();
        if ($plans->isEmpty()) {
            $this->error('No active plans found in the database. Please seed plans first.');
            return Command::FAILURE;
        }

        $specificPlan = null;
        if ($planSlug) {
            $specificPlan = Plan::where('slug', $planSlug)->first();
            if (!$specificPlan) {
                $this->error("Plan with slug '{$planSlug}' not found. Available: " . $plans->pluck('slug')->implode(', '));
                return Command::FAILURE;
            }
        }

        $adminRole = Role::where('name', 'company_admin')->first();
        $employeeRole = Role::where('name', 'employee')->first();
        $defaultPassword = Hash::make('123456');

        $companySectors = [
            'للتقنية والحلول الرقمية',
            'للتجارة والتوزيع',
            'للمقاولات العامة',
            'للخدمات اللوجستية',
            'للاستشارات الإدارية',
            'للتطوير العقاري',
            'للصناعات الغذائية',
            'للرعاية الطبية',
            'للتسويق والإعلام',
            'للحلول الهندسية',
            'للطاقة المتجددة',
            'للسياحة والفندقة',
        ];

        $firstNames = ['محمد', 'أحمد', 'عبدالله', 'علي', 'عمر', 'خالد', 'سعد', 'سلطان', 'فهد', 'يوسف', 'سالم', 'إبراهيم', 'طارق', 'ماجد', 'حسام'];
        $lastNames = ['الغامدي', 'العتيبي', 'الشمري', 'الدوسري', 'القحطاني', 'الزهراني', 'الشهري', 'العنزي', 'المطيري', 'الحربي', 'السبيعي'];

        $this->info("Generating {$count} test companies...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $tableSummary = [];

        DB::beginTransaction();
        try {
            for ($i = 1; $i <= $count; $i++) {
                // Generate unique company code
                do {
                    $code = strtoupper(Str::random(8));
                } while (Company::where('company_code', $code)->exists());

                $sector = $companySectors[array_rand($companySectors)];
                $namePrefix = $firstNames[array_rand($firstNames)];
                $companyName = "شركة {$namePrefix} {$sector} [تجريبي]";
                $domainSlug = strtolower(Str::random(6)) . '.test-seed.local';
                $adminFullName = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
                $adminUsername = "admin_" . strtolower($code);
                $adminEmail = "admin_{$code}@test-seed.local";

                // A. Create Company
                $company = Company::create([
                    'company_name' => $companyName,
                    'company_code' => $code,
                    'domain' => $domainSlug,
                    'timezone' => 'Asia/Riyadh',
                    'phones' => '05' . rand(10000000, 99999999),
                    'address' => 'المملكة العربية السعودية',
                    'email' => $adminEmail,
                    'is_active' => 1,
                ]);

                // B. Create Company Settings
                CompanySetting::create([
                    'company_id' => $company->id,
                    'after_miniute_calculate_delay' => 15,
                    'after_miniute_calculate_early_departure' => 15,
                    'after_miniute_quarterday' => 45,
                    'after_time_half_daycut' => 60,
                    'after_time_allday_daycut' => 120,
                    'monthly_vacation_balance' => 21,
                ]);

                // C. Create Company Admin User First
                $adminUser = User::create([
                    'name' => $adminFullName,
                    'username' => $adminUsername,
                    'email' => $adminEmail,
                    'password' => $defaultPassword,
                    'role' => 'company_admin',
                    'is_active' => 1,
                    'company_id' => $company->id,
                ]);

                if ($adminRole) {
                    $adminUser->assignRole($adminRole);
                }

                // D. Create Initial Defaults (Branch, Department, Shift, Job)
                $branch = Branche::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'name' => 'الفرع الرئيسي',
                    'address' => 'الرياض',
                    'phones' => '011' . rand(1000000, 9999999),
                    'active' => 1,
                    'added_by' => $adminUser->id,
                ]);

                $department = Department::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'name' => 'الإدارة العامة',
                    'phones' => '011' . rand(1000000, 9999999),
                    'active' => 1,
                    'added_by' => $adminUser->id,
                ]);

                $shift = shifts_type::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'type' => 1,
                    'from_time' => '08:00:00',
                    'to_time' => '16:00:00',
                    'total_hour' => 8.00,
                    'active' => 1,
                    'added_by' => $adminUser->id,
                ]);

                $job = Jobs_categories::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'name' => 'مدير تنفيذي',
                    'active' => 1,
                    'added_by' => $adminUser->id,
                ]);

                // E. Create Initial Manager Employee
                $managerEmployee = Employee::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'employee_code' => 1,
                    'first_name' => explode(' ', $adminFullName)[0],
                    'last_name' => explode(' ', $adminFullName)[1] ?? '',
                    'emp_name' => $adminFullName,
                    'emp_email' => $adminEmail,
                    'emp_home_tel' => '05' . rand(10000000, 99999999),
                    'emp_start_date' => now()->format('Y-m-d'),
                    'emp_sal' => 15000,
                    'date' => now()->format('Y-m-d'),
                    'functional_status' => 1,
                    'does_has_attendance' => 1,
                    'sal_cash_or_visa' => 1,
                    'branch_id' => $branch->id,
                    'emp_departments_id' => $department->id,
                    'emp_job_id' => $job->id,
                    'shift_type_id' => $shift->id,
                    'added_by' => $adminUser->id,
                ]);

                $adminUser->update(['employee_id' => $managerEmployee->id]);

                // F. Assign Subscription
                $plan = $specificPlan ?: $plans->random();
                $isFreePlan = ($plan->slug === 'free' || $plan->price_monthly == 0);

                $status = 'active';
                $startDate = now();
                $endDate = null;
                $billingCycle = $isFreePlan ? 'free' : (rand(0, 1) ? 'monthly' : 'yearly');

                if (!$isFreePlan) {
                    if ($includeExpired) {
                        $scenario = $i % 3;
                        if ($scenario === 0) {
                            // Expired (End date 5 days ago)
                            $status = 'expired';
                            $startDate = now()->subMonths(1)->subDays(5);
                            $endDate = now()->subDays(5);
                        } elseif ($scenario === 1) {
                            // Expiring Soon (End date in 5 days)
                            $status = 'active';
                            $startDate = now()->subDays(25);
                            $endDate = now()->addDays(5);
                        } else {
                            // Active normal (End date in 6 months)
                            $status = 'active';
                            $startDate = now()->subDays(10);
                            $endDate = now()->addMonths(6);
                        }
                    } else {
                        // Standard Active
                        $status = 'active';
                        $startDate = now();
                        $endDate = ($billingCycle === 'yearly') ? now()->addYear() : now()->addMonth();
                    }
                }

                $price = $isFreePlan ? 0 : ($billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'billing_cycle' => $billingCycle,
                    'price' => $price,
                    'currency' => 'USD',
                    'status' => $status,
                    'notes' => '[TEST-SEED] Auto-generated test subscription.',
                    'created_by' => 1,
                ]);

                // G. Optional Employees Seeding (Strictly respecting plan capacity limit max_users)
                $maxUsers = (int) ($plan->max_users ?? 5);
                $isUnlimited = ($maxUsers >= 999999 || $maxUsers === 0);

                // Determine effective total employees (strictly capped by plan max_users limit)
                $targetTotal = $employeesPerCompany > 0 ? $employeesPerCompany : 1;
                $effectiveTotal = $isUnlimited ? $targetTotal : min($targetTotal, $maxUsers);

                // Manager employee (code 1) is already created at step E.
                $additionalToSeed = max(0, $effectiveTotal - 1);

                if ($additionalToSeed > 0) {
                    for ($e = 2; $e <= ($additionalToSeed + 1); $e++) {
                        $fName = $firstNames[array_rand($firstNames)];
                        $lName = $lastNames[array_rand($lastNames)];
                        $empFullName = "{$fName} {$lName}";
                        $empEmail = "emp{$e}_{$code}@test-seed.local";

                        $emp = Employee::withoutGlobalScopes()->create([
                            'company_id' => $company->id,
                            'employee_code' => $e,
                            'first_name' => $fName,
                            'last_name' => $lName,
                            'emp_name' => $empFullName,
                            'emp_email' => $empEmail,
                            'emp_home_tel' => '05' . rand(10000000, 99999999),
                            'emp_start_date' => now()->format('Y-m-d'),
                            'emp_sal' => rand(5000, 10000),
                            'date' => now()->format('Y-m-d'),
                            'functional_status' => 1,
                            'does_has_attendance' => 1,
                            'sal_cash_or_visa' => 1,
                            'branch_id' => $branch->id,
                            'emp_departments_id' => $department->id,
                            'shift_type_id' => $shift->id,
                            'added_by' => $adminUser->id,
                            'notes' => '[TEST-SEED]',
                        ]);

                        $user = User::withoutGlobalScopes()->create([
                            'name' => $empFullName,
                            'username' => "user_{$code}_{$e}",
                            'email' => $empEmail,
                            'password' => $defaultPassword,
                            'role' => 'employee',
                            'is_active' => 1,
                            'company_id' => $company->id,
                            'employee_id' => $emp->id,
                        ]);

                        if ($employeeRole) {
                            $user->assignRole($employeeRole);
                        }
                    }
                }

                $tableSummary[] = [
                    'Company Name' => $companyName,
                    'Code' => $code,
                    'Plan' => $plan->name,
                    'Billing' => $billingCycle,
                    'Sub Status' => $status,
                    'Admin User' => $adminUsername,
                    'Employees' => "{$effectiveTotal} / " . ($isUnlimited ? '∞' : $maxUsers),
                ];

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine(2);

            $this->info("Successfully generated {$count} test companies!");
            $this->table(
                ['Company Name', 'Code', 'Plan', 'Billing', 'Sub Status', 'Admin User', 'Employees'],
                $tableSummary
            );

            $this->info('Admin Password for all accounts: 123456');
            $this->comment('Tip: To remove all generated test companies later, run:');
            $this->line('php artisan system:seed-companies --cleanup');

            return Command::SUCCESS;
        } catch (Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            $this->error("Failed to seed companies: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
