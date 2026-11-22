<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\BioTimeService;
use Spatie\Permission\Models\Role;
use Exception;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with(['currentSubscription.plan', 'latestSubscription.plan'])
            ->withCount([
                'users as active_users_count' => function ($query) {
                    $query->where('is_active', 1);
                },
                'employees as total_employees_count'
            ])
            ->orderBy('created_at', 'DESC')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $companies
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:250',
            'domain' => 'nullable|string|max:100|unique:companies,domain',
            'admin_name' => 'required|string|max:100',
            'auth_username' => 'required|string|max:100',
            'admin_email' => 'required|email|max:100',
            'admin_password' => 'required|string|min:6',
        ]);

        try {
            DB::beginTransaction();

            // Generate a random 8-character alphanumeric string
            do {
                $code = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(10));
            } while (Company::where('company_code', $code)->exists());

            // 1. Create Company
            $company = Company::create([
                'company_name' => $request->company_name,
                'company_code' => $code,
                'domain' => $request->domain,
                'timezone' => $request->timezone ?? 'Asia/Riyadh',
                'phones' => '',
                'address' => '',
                'email' => $request->admin_email,
                'is_active' => 1,
            ]);

            // 2. Create Initial Company Settings
            CompanySetting::create([
                'company_id' => $company->id,
                'after_miniute_calculate_delay' => 15,
                'after_miniute_calculate_early_departure' => 15,
                'after_miniute_quarterday' => 45,
                'after_time_half_daycut' => 60,
                'after_time_allday_daycut' => 120,
                'monthly_vacation_balance' => 21,
            ]);

            // 3. Create Company Admin User
            $adminUser = User::create([
                'name' => $request->admin_name,
                'username' => $request->auth_username,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'role' => 'company_admin',
                'is_active' => 1,
                'company_id' => $company->id,
            ]);

            // Create default roles for company and assign Manager role to company admin
            $adminRole = Role::where('name', 'company_admin')->first();
            if ($adminRole) {
                $adminUser->assignRole($adminRole);
            }

            // 4. Create Employee record for the Manager
            $employee = Employee::create([
                'employee_code' => 1,
                'emp_name' => $request->admin_name,
                'emp_email' => $request->admin_email,
                'company_id' => $company->id,
                'added_by' => $adminUser->id,
                'date' => date('Y-m-d'),
                'functional_status' => 1,
                'does_has_attendance' => 1,
                'sal_cash_or_visa' => 1,
            ]);

            // Link the Employee ID to the User
            $adminUser->update(['employee_id' => $employee->id]);

            // 5. Assign Initial Free Plan Subscription
            $freePlan = Plan::where('slug', 'free')->first() ?? Plan::first();
            if ($freePlan) {
                Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $freePlan->id,
                    'start_date' => now(),
                    'end_date' => null,

                    'billing_cycle' => 'free',
                    'price' => 0.00,
                    'currency' => 'USD',
                    'status' => 'active',
                    'notes' => 'اشتراك تلقائي في الباقة المجانية عند إنشاء الشركة',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            // 6. BioTime Sync outside DB transaction
            try {
                $bioTimeService = new BioTimeService();
                $bioTimeService->createArea($company->company_code, $company->company_name);
                $bioTimeService->createEmployee($employee->employee_code, $employee->emp_name, $company->company_code);
            } catch (Exception $bioEx) {
                Log::warning('BioTime sync skipped during SuperAdmin company creation: ' . $bioEx->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => __('messages.company_created'),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating company: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'company_name' => 'required|string|max:250',
            'domain' => 'nullable|string|max:100|unique:companies,domain,' . $id,
            'is_active' => 'required|boolean',
        ]);

        try {
            $company = Company::findOrFail($id);
            
            $company->update([
                'company_name' => $request->company_name,
                'domain' => $request->domain,
                'is_active' => $request->is_active,
            ]);

            return response()->json([
                'status' => true,
                'message' => __('messages.updated_successfully'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating company: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => __('messages.server_error'),
            ], 500);
        }
    }
}

