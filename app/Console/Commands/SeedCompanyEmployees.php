<?php

namespace App\Console\Commands;

use App\Models\Branche;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Jobs_categories;
use App\Models\shifts_type;
use App\Models\Subscription;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SeedCompanyEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company:seed-employees 
                            {company? : Company UUID, code, or name} 
                            {--count=50 : Number of test employees to generate} 
                            {--with-users : Create user login accounts for these employees}
                            {--force : Force creating employees even if exceeding plan limits}
                            {--cleanup : Remove all previously seeded test employees for this company}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quickly seed test employees and users for a company with strict adherence to plan limits (or --force to override).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $companyIdentifier = $this->argument('company');
        $count = (int) $this->option('count');
        $withUsers = (bool) $this->option('with-users');
        $force = (bool) $this->option('force');
        $cleanup = (bool) $this->option('cleanup');

        // 1. Resolve Company
        $company = null;
        if ($companyIdentifier) {
            $company = Company::where('id', $companyIdentifier)
                ->orWhere('company_code', $companyIdentifier)
                ->orWhere('company_name', 'like', "%{$companyIdentifier}%")
                ->first();
        }

        if (!$company) {
            $companies = Company::all(['id', 'company_name', 'company_code']);
            if ($companies->isEmpty()) {
                $this->error('No companies exist in the system.');
                return Command::FAILURE;
            }

            $choices = $companies->mapWithKeys(function ($c) {
                return [$c->id => "{$c->company_name} ({$c->company_code})"];
            })->toArray();

            $selectedId = $this->choice('Select the company to seed employees for:', $choices);
            $company = Company::find($selectedId);
        }

        $this->info("Selected Company: {$company->company_name} (Code: {$company->company_code})");

        // 2. Cleanup mode
        if ($cleanup) {
            $this->warn("Cleaning up test employees tagged with '[TEST-SEED]' for company: {$company->company_name}...");
            $testEmployees = Employee::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('notes', 'like', '%[TEST-SEED]%')
                ->get();

            $empCount = $testEmployees->count();
            if ($empCount === 0) {
                $this->info('No test seed records found to clean up.');
                return Command::SUCCESS;
            }

            $userIds = User::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereIn('employee_id', $testEmployees->pluck('id'))
                ->pluck('id');

            DB::beginTransaction();
            try {
                User::withoutGlobalScopes()->whereIn('id', $userIds)->delete();
                Employee::withoutGlobalScopes()->whereIn('id', $testEmployees->pluck('id'))->delete();
                DB::commit();
                $this->info("Successfully deleted {$empCount} test employees and associated users.");
                return Command::SUCCESS;
            } catch (Exception $e) {
                DB::rollBack();
                $this->error("Cleanup failed: {$e->getMessage()}");
                return Command::FAILURE;
            }
        }

        // Check active subscription & plan limit
        $activeSub = Subscription::where('company_id', $company->id)
            ->where('status', 'active')
            ->with('plan')
            ->latest('id')
            ->first();

        $plan = $activeSub ? $activeSub->plan : null;
        $maxUsers = $plan ? (int) $plan->max_users : 5;
        $isUnlimited = ($maxUsers >= 999999 || $maxUsers === 0);

        $currentEmployees = Employee::withoutGlobalScopes()->where('company_id', $company->id)->count();

        if (!$isUnlimited && !$force) {
            $remainingSlots = max(0, $maxUsers - $currentEmployees);
            if ($remainingSlots === 0) {
                $this->error("Company has already reached its maximum plan capacity ({$currentEmployees} / {$maxUsers} employees).");
                $this->comment("Use --force to override plan limits if needed for edge-case testing.");
                return Command::FAILURE;
            }

            if ($count > $remainingSlots) {
                $this->warn("Requested {$count} employees, but plan only allows {$remainingSlots} more (limit: {$maxUsers}, current: {$currentEmployees}).");
                $this->line("Adjusting count to {$remainingSlots} to strictly respect plan capacity.");
                $count = $remainingSlots;
            }
        }

        // 3. Prepare Batch Creation
        $adminUser = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('role', 'company_admin')
            ->first();
        $adminId = $adminUser ? $adminUser->id : 1;

        $branch = Branche::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $department = Department::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $job = Jobs_categories::withoutGlobalScopes()->where('company_id', $company->id)->first();
        $shift = shifts_type::withoutGlobalScopes()->where('company_id', $company->id)->first();

        // Get max current employee_code
        $lastCode = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->max('employee_code') ?? 1000;

        $employeeRole = Role::where('name', 'employee')->first();
        $passwordHash = Hash::make('123456');

        $firstNames = ['محمد', 'أحمد', 'عبدالله', 'علي', 'عمر', 'خالد', 'سعد', 'سلطان', 'فهد', 'يوسف', 'سالم', 'إبراهيم', 'طارق', 'ماجد', 'حسام'];
        $lastNames = ['الغامدي', 'العتيبي', 'الشمري', 'الدوسري', 'القحطاني', 'الزهراني', 'الشهري', 'العنزي', 'المطيري', 'الحربي', 'السبيعي'];

        $this->info("Generating {$count} test employees" . ($withUsers ? ' with login accounts' : '') . '...');
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        DB::beginTransaction();
        try {
            for ($i = 1; $i <= $count; $i++) {
                $code = ++$lastCode;
                $firstName = $firstNames[array_rand($firstNames)];
                $middleName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $fullName = "{$firstName} {$middleName} {$lastName}";
                $email = "emp{$code}_{$company->company_code}@test.local";
                $username = "emp_{$company->company_code}_{$code}";

                $employee = Employee::withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'employee_code' => $code,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'emp_name' => $fullName,
                    'emp_email' => $email,
                    'emp_home_tel' => '05' . rand(10000000, 99999999),
                    'emp_start_date' => now()->subMonths(rand(1, 12))->format('Y-m-d'),
                    'day_price' => rand(100, 300),
                    'emp_sal' => rand(4000, 12000),
                    'date' => now()->format('Y-m-d'),
                    'functional_status' => 1,
                    'does_has_attendance' => 1,
                    'sal_cash_or_visa' => 1,
                    'branch_id' => $branch?->id,
                    'emp_departments_id' => $department?->id,
                    'emp_job_id' => $job?->id,
                    'shift_type_id' => $shift?->id,
                    'added_by' => $adminId,
                    'notes' => '[TEST-SEED] Test batch employee for capacity simulation.',
                ]);

                if ($withUsers) {
                    $user = User::withoutGlobalScopes()->create([
                        'name' => $fullName,
                        'username' => $username,
                        'email' => $email,
                        'password' => $passwordHash,
                        'role' => 'employee',
                        'is_active' => 1,
                        'company_id' => $company->id,
                        'employee_id' => $employee->id,
                    ]);

                    if ($employeeRole) {
                        $user->assignRole($employeeRole);
                    }
                }

                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine(2);

            $totalEmployees = Employee::withoutGlobalScopes()->where('company_id', $company->id)->count();
            $totalActiveUsers = User::withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', 1)->count();

            $this->info("Successfully created {$count} test employees!");
            $this->table(
                ['Metric', 'Current Count'],
                [
                    ['Total Employees in Company', $totalEmployees],
                    ['Total Active Users (Logins)', $totalActiveUsers],
                ]
            );

            $this->comment('Tip: To clean up these generated records later, run:');
            $this->line("php artisan company:seed-employees {$company->company_code} --cleanup");

            return Command::SUCCESS;
        } catch (Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            $this->error("Failed to seed employees: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
