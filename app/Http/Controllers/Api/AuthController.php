<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BioTimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function registerCompany(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:250',
            'admin_name' => 'required|string|max:100',
            'auth_username' => 'required|string|max:100|unique:users,username',
            'admin_email' => 'required|email|max:100|unique:users,email',
            'admin_password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:30',
        ]);

        try {
            DB::beginTransaction();

            // Generate unique 10-character alphanumeric company code
            do {
                $code = Str::upper(Str::random(10));
            } while (Company::where('company_code', $code)->exists());

            // 1. Create Company (domain is null for self-registered free companies)
            $company = Company::create([
                'company_name' => $request->company_name,
                'company_code' => $code,
                'domain' => null,
                'phones' => $request->phone ?? '',
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

            // Assign Company Admin Role
            $adminRole = Role::where('name', 'company_admin')->first();
            if ($adminRole) {
                $adminUser->assignRole($adminRole);
            }

            // 4. Create Employee record for the Manager
            $employee = Employee::create([
                'employee_code' => 1,
                'emp_name' => $request->admin_name,
                'emp_email' => $request->admin_email,
                'emp_home_tel' => $request->phone ?? null,
                'company_id' => $company->id,
                'added_by' => $adminUser->id,
                'date' => date('Y-m-d'),
                'functional_status' => 1,
                'does_has_attendance' => 1,
                'sal_cash_or_visa' => 1,
            ]);

            // Link Employee ID to the User
            $adminUser->update(['employee_id' => $employee->id]);

            // 5. Assign Free Plan Subscription
            $freePlan = Plan::where('slug', 'free')->first() ?? Plan::where('price_monthly', 0)->first() ?? Plan::first();
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
                    'created_by' => $adminUser->id,
                ]);
            }

            DB::commit();

            // 6. BioTime Sync outside DB transaction (graceful so it doesn't block registration if BioTime service is not reachable)
            try {
                $bioTimeService = new BioTimeService();
                $bioTimeService->createArea($company->company_code, $company->company_name);
                $bioTimeService->createEmployee($employee->employee_code, $employee->emp_name, $company->company_code);
            } catch (\Exception $bioEx) {
                Log::warning('BioTime sync skipped during company registration: ' . $bioEx->getMessage());
            }

            // Generate token and load profile for instant login
            $token = $adminUser->createToken('auth_token', [$adminUser->role])->plainTextToken;
            $permissions = Permission::pluck('name');
            if ($adminUser->employee_id) {
                $adminUser->load(['employee.job:id,name', 'employee.department:id,name', 'employee.branch:id,name']);
            }

            return response()->json([
                'status' => true,
                'message' => __('messages.company_registered_successfully'),
                'data' => [
                    'user' => $adminUser,
                    'token' => $token,
                    'role' => $adminUser->role,
                    'permissions' => $permissions,
                    'company' => $company
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error registering company: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $candidates = User::where(function ($q) use ($request) {
            $q->where('username', $request->username)
              ->orWhere('email', $request->username);
        })->get();

        $user = $candidates->first(function (User $candidate) use ($request) {
            return Hash::check($request->password, $candidate->password);
        });

        if (! $user) {
            throw ValidationException::withMessages([
                'username' => [__('messages.login_failed')],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => [__('messages.account_disabled')],
            ]);
        }

        // Check subscription status for company users
        if ($user->role !== 'super_admin' && $user->company_id) {
            $subStatus = \App\Services\SubscriptionService::getCompanySubscriptionStatus($user->company_id);
            if (!empty($subStatus['is_expired']) && empty($subStatus['is_free'])) {
                throw ValidationException::withMessages([
                    'username' => [__('messages.subscription_expired_login_blocked')],
                ]);
            }
        }

        // Delete old tokens for the same device/session type
        $tokenName = $request->input('device_name', 'auth_token');
        $user->tokens()->where('name', $tokenName)->delete();

        // Create token with abilities based on role
        $token = $user->createToken($tokenName, [$user->role])->plainTextToken;
        
        // Load related profile based on role (or fallback to employee_id if present)
        if ($user->employee_id) {
            $user->load(['employee.job:id,name', 'employee.department:id,name', 'employee.branch:id,name']);
        }
                
        // If user is a company_admin, we give them all permissions by default in the frontend,
        // If user is super_admin, they don't need these specific company permissions.
        // otherwise we only return their assigned Spatie permissions.
        $permissions = $this->getPermissionsForUser($user);

        return response()->json([
            'status' => true,
            'message' => __('messages.login_success'),
            'data' => [
                'user' => $user,
                'token' => $token,
                'role' => $user->role,
                'permissions' => $permissions
            ]
        ]);
    }

    protected function getPermissionsForUser(User $user)
    {
        if ($user->role === 'company_admin') {
            return Permission::pluck('name');
        }
        if ($user->role === 'super_admin') {
            return collect([]);
        }

        $permissions = $user->getAllPermissions()->pluck('name');

        // Fallback: If no Spatie roles are attached, but user has string role 'employee'
        if ($permissions->isEmpty() && $user->role === 'employee') {
            $employeeRole = Role::where('name', 'employee')->first();

            if ($employeeRole) {
                $permissions = $employeeRole->permissions->pluck('name');
            }
        }

        return $permissions;
    }

    public function logout(Request $request)
    {
        // Revoke current token
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.logout_success')
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        
        if ($user->employee_id) {
            $user->load(['employee.job:id,name', 'employee.department:id,name', 'employee.branch:id,name']);
        }
        
        $permissions = $this->getPermissionsForUser($user);

        $userData = $user->toArray();
        $userData['permissions'] = $permissions;

        return response()->json([
            'status' => true,
            'data' => $userData
        ]);
    }

    public function updateProfile(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'username' => 'nullable|string|unique:users,username,' . $user->id,
            'password' => 'nullable|string|min:6|confirmed',
            'first_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'emp_home_tel' => 'nullable|string|max:50',
            'staies_address' => 'nullable|string|max:300',
        ]);

        $credentialsChanged = false;
        $userData = [];

        if ($request->has('email') && $request->email !== null && $request->email !== $user->email) {
            $userData['email'] = $request->email;
            $credentialsChanged = true;
        }

        if ($request->has('username') && $request->username !== null && $request->username !== $user->username) {
            $userData['username'] = $request->username;
            $credentialsChanged = true;
        }
        
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
            $credentialsChanged = true;
        }
        
        if (!empty($userData)) {
            $user->update($userData);
        }

        if ($user->employee_id) {
            $employee = $user->employee;
            if ($employee) {
                $employeeData = $request->only([
                    'first_name',
                    'middle_name',
                    'last_name',
                    'emp_home_tel',
                    'staies_address'
                ]);
                // Filter only present keys
                $employeeData = array_filter($employeeData, function($val) {
                    return $val !== null;
                });
                if (!empty($employeeData)) {
                    $employee->update($employeeData);
                    $employee->refresh();
                    if ($employee->emp_name) {
                        $user->name = $employee->emp_name;
                        $user->save();
                    }
                }
            }
        }

        $user->refresh();
        if ($user->employee_id) {
            $user->load(['employee.job:id,name', 'employee.department:id,name', 'employee.branch:id,name']);
        }

        $userDataResponse = $user->toArray();
        $userDataResponse['permissions'] = $this->getPermissionsForUser($user);

        $message = $credentialsChanged 
            ? __('messages.credentials_updated') 
            : __('messages.profile_updated');

        return response()->json([
            'status' => true,
            'message' => $message,
            'credentials_changed' => $credentialsChanged,
            'data' => $userDataResponse
        ]);
    }
}
