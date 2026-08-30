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
use App\Services\BioTimeService;
use Spatie\Permission\Models\Role;
use Exception;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::orderBy('created_at', 'DESC')->get();
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
                'is_active' => 1,
            ]);

            // 2. Create Initial Company Settings
            CompanySetting::create([
                'company_id' => $company->id,
                'system_status' => 1,
                'after_miniute_calculate_delay' => 15,
                'after_miniute_calculate_early_departure' => 15,
                'after_miniute_quarterday' => 45,
                'after_time_half_daycut' => 60,
                'after_time_allday_daycut' => 120,
                'phones' => '',
                'address' => '',
                'email' => $request->admin_email,
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

            // 5. Create Area in BioTime
            // If BioTime fails or area code is duplicated, an exception will be thrown,
            // caught by the outer catch block, and the database transaction will be rolled back.
            $bioTimeService = new BioTimeService();
            $bioTimeService->createArea($company->company_code, $company->company_name);

            // 6. Create the Manager in BioTime
            $bioTimeService->createEmployee($employee->employee_code, $employee->emp_name, $company->company_code);


            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم إنشاء الشركة وحساب المدير وربطها بنظام البصمة بنجاح',
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
                'message' => 'تم تحديث بيانات الشركة بنجاح',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating company: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تحديث الشركة',
            ], 500);
        }
    }
}
