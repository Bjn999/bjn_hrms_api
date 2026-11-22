<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
use App\Models\Branche;
use App\Models\Department;
use App\Models\Jobs_categories;
use App\Models\Qualification;
use App\Models\Blood_Group;
use App\Models\Nationality;
use App\Models\Religion;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\Center;
use App\Models\shifts_type;
use App\Models\Resignation;
use App\Models\Language;
use App\Models\Social_Status_Type;
use App\Models\Military_Status;
use App\Models\Driving_license_type;
use App\Models\Main_salary_employee;
use App\Models\Employee_fixed_allowance;
use App\Models\Employee_File;
use App\Models\Allowance;
use App\Models\Employee_salary_archive;
use App\Services\BioTimeService;
use App\Traits\GeneralTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class EmployeesController extends Controller
{
    use GeneralTrait;
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $company_id = $user->company_id;
            $query = Employee::with(['branch:id,name', 'department:id,name', 'job:id,name', 'added:id,name', 'updatedby:id,name', 'user:id,employee_id,username,email,is_active,initial_password', 'user.roles:id,name,display_name'])
                ->where('company_id', $company_id);

            // Search filters
            if ($request->emp_name) {
                $searchTerms = explode(' ', trim($request->emp_name));
                $query->where(function ($q) use ($searchTerms) {
                    foreach ($searchTerms as $term) {
                        if (!empty($term)) {
                            $q->where('emp_name', 'like', '%' . $term . '%');
                        }
                    }
                });
            }
            if ($request->employee_code) {
                $query->where('employee_code', 'like', '%' . $request->employee_code . '%');
            }
            if (isset($request->active) && $request->active !== '') {
                $query->where('functional_status', $request->active);
            }

            $data = $query->orderBy('id', 'DESC')->get();

            $salaryCounts = Main_salary_employee::where('company_id', $company_id)
                ->select('employee_code', DB::raw('count(*) as total'))
                ->groupBy('employee_code')
                ->pluck('total', 'employee_code');

            foreach ($data as $info) {
                $info->counterUsed = (int) ($salaryCounts[$info->employee_code] ?? 0);
                if ($info->user) {
                    $info->user->makeVisible('initial_password');
                }
            }

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function get_required_data(Request $request)
    {
        try {
            $company_id = auth()->user()->company_id;
            $data = [
                'branches' => Branche::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'departments' => Department::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'jobs' => Jobs_categories::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'qualifications' => Qualification::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'blood_groups' => Blood_Group::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'nationalities' => Nationality::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'religions' => Religion::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'countries' => Country::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'governorates' => Governorate::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name', 'country_id']),
                'cities' => Center::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name', 'governorate_id']),
                'shifts' => shifts_type::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'type', 'from_time', 'to_time', 'total_hour']),
                'resignations' => Resignation::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'languages' => Language::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'social_status' => Social_Status_Type::where(['active' => 1])->get(['id', 'name']),
                'military_status' => Military_Status::where(['active' => 1])->get(['id', 'name']),
                'driving_license_types' => Driving_license_type::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'allowances' => Allowance::where(['company_id' => $company_id, 'active' => 1])->get(['id', 'name']),
                'roles' => Role::whereNotIn('name', ['super_admin', 'company_admin'])->get(['id', 'name', 'display_name']),
            ];

            // Add Subscription usage status
            $company = Company::find($company_id);
            $currentSub = $company ? $company->currentSubscription()->with('plan')->first() : null;
            $totalEmployees = Employee::where('company_id', $company_id)->count();
            $maxUsers = ($currentSub && $currentSub->plan) ? $currentSub->plan->max_users : 5;
            $isUnlimited = $maxUsers >= 999999 || $maxUsers === 0;

            $data['subscription_usage'] = [
                'plan_name' => $currentSub && $currentSub->plan ? $currentSub->plan->name : 'الباقة المجانية',
                'total_employees_count' => $totalEmployees,
                'max_users' => $maxUsers,
                'is_unlimited' => $isUnlimited,
                'can_add_employee' => $isUnlimited || ($totalEmployees < $maxUsers),
            ];


            return response()->json([
                'status' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            $validator = Validator::make($request->all(), [
                'emp_name' => 'required',
                'branch_id' => 'required',
                'emp_departments_id' => 'required',
                'emp_job_id' => 'required',
                'emp_sal' => 'required|numeric',
                'emp_start_date' => 'required|date',
                'emp_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
                'emp_cv' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Employee::where(['company_id' => $user->company_id, 'emp_name' => $request->emp_name])->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'اسم الموظف مسجل من قبل'], 422);
            }

            if ($request->zketo_code) {
                $checkZketo = Employee::where(['company_id' => $user->company_id, 'zketo_code' => $request->zketo_code])->first();
                if ($checkZketo) {
                    return response()->json(['status' => false, 'message' => 'كود البصمة مسجل لموظف آخر'], 422);
                }
            }

            // Check Subscription total employees limit
            $company = Company::find($user->company_id);
            $currentSub = $company ? $company->currentSubscription()->with('plan')->first() : null;
            if ($currentSub && $currentSub->plan && $currentSub->plan->max_users > 0 && $currentSub->plan->max_users < 999999) {
                $totalEmployees = Employee::where('company_id', $user->company_id)->count();
                if ($totalEmployees >= $currentSub->plan->max_users) {
                    return response()->json(['status' => false, 'message' => __('messages.max_users_limit_reached')], 422);
                }
            }

            if ($request->role_id) {
                $role = Role::find($request->role_id);
                if ($role && in_array($role->name, ['company_admin', 'super_admin'])) {
                    return response()->json(['status' => false, 'message' => __('messages.cannot_assign_company_admin_role')], 422);
                }
            }

            $columns = Schema::getColumnListing('employees');
            $dataToInsert = $request->only($columns);
            unset(
                $dataToInsert['id'],
                $dataToInsert['employee_code'],
                $dataToInsert['company_id'],
                $dataToInsert['added_by'],
                $dataToInsert['updated_by'],
                $dataToInsert['date'],
                $dataToInsert['emp_photo'],
                $dataToInsert['emp_cv']
            );

            if ($request->hasFile('emp_photo')) {
                $file = $request->file('emp_photo');
                $filename = time() . '_' . uniqid() . '_photo.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $dataToInsert['emp_photo'] = $filename;
            }

            if ($request->hasFile('emp_cv')) {
                $file = $request->file('emp_cv');
                $filename = time() . '_' . uniqid() . '_cv.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $dataToInsert['emp_cv'] = $filename;
            }

            DB::beginTransaction();

            $last_employee = Employee::where('company_id', $user->company_id)
                ->lockForUpdate()
                ->orderBy('employee_code', 'DESC')
                ->first();
            $employee_code = $last_employee ? $last_employee->employee_code + 1 : 1;

            $dataToInsert['employee_code'] = $employee_code;
            $dataToInsert['company_id'] = $user->company_id;
            $dataToInsert['added_by'] = $user->id;
            $dataToInsert['date'] = date('Y-m-d');

            $employee = Employee::create($dataToInsert);

            // Create ESS User Account for the employee with a unique 8-digit random initial password
            $username = $company->company_code . '-EMP' . $employee_code;
            $initialPassword = (string) random_int(10000000, 99999999);

            $createdUser = User::create([
                'name' => $request->emp_name,
                'username' => $username,
                'email' => $request->emp_email ?? null,
                'password' => Hash::make($initialPassword),
                'initial_password' => $initialPassword,
                'role' => 'employee',
                'employee_id' => $employee->id,
                'company_id' => $user->company_id,
                'is_active' => 1,
            ]);

            if ($request->role_id) {
                $role = Role::find($request->role_id);
                if ($role) {
                    $createdUser->assignRole($role);
                    $createdUser->update(['role' => $role->name]);
                }
            }

            Employee_salary_archive::create([
                'employee_id' => $employee->id,
                'value' => $employee->emp_sal,
                'added_by' => $user->id,
                'company_id' => $user->company_id
            ]);

            DB::commit();

            // Add to BioTime outside DB transaction so network latency or failure does not roll back employee creation
            try {
                $bioTimeService = new BioTimeService();
                $bioTimeCode = $request->zketo_code ? $request->zketo_code : $employee_code;
                $bioTimeService->createEmployee($bioTimeCode, $request->emp_name, $company->company_code);
            } catch (\Exception $bioEx) {
                \Illuminate\Support\Facades\Log::warning('BioTime employee sync skipped: ' . $bioEx->getMessage());
            }

            return response()->json(['status' => true, 'message' => __('messages.saved_successfully')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = auth()->user();
            // setPermissionsTeamId($user->company_id);

            $data = Employee::with([
                'branch',
                'department',
                'job',
                'qualification',
                'blood_group',
                'nationality',
                'religion',
                'country',
                'governorate',
                'city',
                'shift',
                'language',
                'social_status',
                'resignation',
                'added:id,name',
                'updatedby:id,name',
                'fixedAllowances.allowance',
                'files',
                'user.roles:id,name,display_name'
            ])->where(['id' => $id, 'company_id' => $user->company_id])->first();

            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $roleId = null;
            $isCompanyAdmin = false;
            if ($data->user) {
                $isCompanyAdmin = ($data->user->role === 'company_admin' || $data->user->hasRole('company_admin'));
                if ($data->user->roles && $data->user->roles->isNotEmpty()) {
                    $roleId = $data->user->roles->first()->id;
                } elseif ($data->user->role) {
                    $matchedRole = \Spatie\Permission\Models\Role::where('name', $data->user->role)->first();
                    if ($matchedRole) {
                        $roleId = $matchedRole->id;
                    }
                }
            }

            $dataArray = $data->toArray();
            $dataArray['role_id'] = $roleId ? (string)$roleId : '';
            $dataArray['is_company_admin'] = $isCompanyAdmin;

            return response()->json([
                'status' => true,
                'data' => $dataArray
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $data = Employee::where(['id' => $id, 'company_id' => $user->company_id])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $validator = Validator::make($request->all(), [
                'emp_name' => 'required',
                'branch_id' => 'required',
                'emp_departments_id' => 'required',
                'emp_job_id' => 'required',
                'emp_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
                'emp_cv' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Employee::where(['company_id' => $user->company_id, 'emp_name' => $request->emp_name])->where('id', '!=', $id)->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'اسم الموظف مسجل من قبل'], 422);
            }

            $columns = Schema::getColumnListing('employees');
            $requestData = $request->only($columns);
            unset(
                $requestData['id'],
                $requestData['employee_code'],
                $requestData['company_id'],
                $requestData['added_by'],
                $requestData['date'],
                $requestData['emp_photo'],
                $requestData['emp_cv']
            );
            $requestData['updated_by'] = $user->id;

            if ($request->hasFile('emp_photo')) {
                if ($data->emp_photo && file_exists(public_path('assets/admin/uploads/' . $data->emp_photo))) {
                    unlink(public_path('assets/admin/uploads/' . $data->emp_photo));
                }
                $file = $request->file('emp_photo');
                $filename = time() . '_' . uniqid() . '_photo.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $requestData['emp_photo'] = $filename;
            }

            if ($request->hasFile('emp_cv')) {
                if ($data->emp_cv && file_exists(public_path('assets/admin/uploads/' . $data->emp_cv))) {
                    unlink(public_path('assets/admin/uploads/' . $data->emp_cv));
                }
                $file = $request->file('emp_cv');
                $filename = time() . '_' . uniqid() . '_cv.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $requestData['emp_cv'] = $filename;
            }

            DB::beginTransaction();

            $old_salary = $data->emp_sal;
            $data->update($requestData);

            if ($old_salary != $data->emp_sal) {
                Employee_salary_archive::create([
                    'employee_id' => $data->id,
                    'value' => $data->emp_sal,
                    'added_by' => $user->id,
                    'company_id' => $user->company_id
                ]);
            }

            if (isset($requestData['does_has_fixed_allowance']) && $requestData['does_has_fixed_allowance'] == 0) {
                Employee_fixed_allowance::where(['company_id' => $user->company_id, 'employee_id' => $id])->delete();
            }

            if ($request->has('role_id')) {
                $empUser = User::where('employee_id', $id)->where('company_id', $user->company_id)->first();
                if ($empUser) {
                    // Prevent changing role if user is company_admin
                    if ($empUser->role === 'company_admin' || $empUser->hasRole('company_admin')) {
                        // Protected: cannot modify company_admin role
                    } else {
                        if ($request->role_id) {
                            $role = Role::find($request->role_id);
                            if ($role && in_array($role->name, ['company_admin', 'super_admin'])) {
                                DB::rollBack();
                                return response()->json(['status' => false, 'message' => __('messages.cannot_assign_company_admin_role')], 422);
                            }
                            if ($role) {
                                $empUser->syncRoles([$role]);
                                $empUser->update(['role' => $role->name]);
                            }
                        } else {
                            $empUser->syncRoles([]);
                            $empUser->update(['role' => 'employee']);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json(['status' => true, 'message' => __('messages.updated_successfully')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $data = Employee::where(['id' => $id, 'company_id' => $user->company_id])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
            }

            // 1. Prevent self-deletion
            if ($user->employee_id == $data->id) {
                return response()->json(['status' => false, 'message' => 'عفواً، لا يمكنك حذف حسابك الشخصي / مدير الشركة'], 422);
            }

            // 2. Check if employee has salary records
            $counterUsed = Main_salary_employee::where(['company_id' => $user->company_id, 'employee_code' => $data->employee_code])->count();
            if ($counterUsed > 0) {
                return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف الموظف لأنه لديه سجلات رواتب في النظام'], 422);
            }

            DB::beginTransaction();

            // 3. Delete associated user account if exists, updating any references first
            $employeeUser = User::where('employee_id', $data->id)->where('company_id', $user->company_id)->first();
            if ($employeeUser) {
                // Reassign any employees added by this user to the current admin user to prevent foreign key error
                Employee::where('added_by', $employeeUser->id)->update(['added_by' => $user->id]);
                $employeeUser->tokens()->delete();
                $employeeUser->delete();
            }

            $data->delete();
            DB::commit();
            return response()->json(['status' => true, 'message' => __('messages.deleted')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function addFixedAllowance(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $company_id = $user->company_id;

            $employee = Employee::where(['company_id' => $company_id, 'id' => $id])->first();
            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], 404);
            }

            $validator = Validator::make($request->all(), [
                'allowance_id' => 'required|integer',
                'value' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            // تأكد من ان البدل لا يتكرر ابداً لنفس الموظف
            $checkExist = Employee_fixed_allowance::where([
                'company_id' => $company_id,
                'allowance_id' => $request->allowance_id,
                'employee_id' => $id
            ])->first();

            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'عفواً هذا البدل مسجل لهذا الموظف من قبل'], 422);
            }

            $dataToInsert = [
                'employee_id' => $id,
                'allowance_id' => $request->allowance_id,
                'value' => $request->value,
                'added_by' => $user->id,
                'company_id' => $company_id,
            ];

            $flag = Employee_fixed_allowance::create($dataToInsert);

            if ($flag) {
                // لو يوجد راتب مفتوح للموظف نعيد احتسابه
                $currentSalary = Main_salary_employee::where(['company_id' => $company_id, 'employee_code' => $employee->employee_code, 'is_archived' => 0])->first();
                if ($currentSalary) {
                    $this->recaculate_main_salary_employee($currentSalary->id);
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'تم إضافة البدل بنجاح',
                'data' => $flag->load('allowance')
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function updateFixedAllowance(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $company_id = $user->company_id;

            $fixedAllowance = \App\Models\Employee_fixed_allowance::where(['company_id' => $company_id, 'id' => $id])->first();
            if (!$fixedAllowance) {
                return response()->json(['status' => false, 'message' => 'البدل غير موجود'], 404);
            }

            $validator = Validator::make($request->all(), [
                'value' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $fixedAllowance->update([
                'value' => $request->value,
                'updated_by' => $user->id
            ]);

            $employee = \App\Models\Employee::where(['company_id' => $company_id, 'id' => $fixedAllowance->employee_id])->first();
            if ($employee) {
                $currentSalary = \App\Models\Main_salary_employee::where(['company_id' => $company_id, 'employee_code' => $employee->employee_code, 'is_archived' => 0])->first();
                if ($currentSalary) {
                    $this->recaculate_main_salary_employee($currentSalary->id);
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'تم التعديل بنجاح',
                'data' => $fixedAllowance->load('allowance')
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function deleteFixedAllowance($id)
    {
        try {
            $user = auth()->user();
            $company_id = $user->company_id;

            $fixedAllowance = Employee_fixed_allowance::where(['company_id' => $company_id, 'id' => $id])->first();
            if (!$fixedAllowance) {
                return response()->json(['status' => false, 'message' => 'البدل غير موجود'], 404);
            }

            $employee = Employee::where(['company_id' => $company_id, 'id' => $fixedAllowance->employee_id])->first();

            $fixedAllowance->delete();

            if ($employee) {
                // لو يوجد راتب مفتوح للموظف نعيد احتسابه
                $currentSalary = Main_salary_employee::where(['company_id' => $company_id, 'employee_code' => $employee->employee_code, 'is_archived' => 0])->first();
                if ($currentSalary) {
                    $this->recaculate_main_salary_employee($currentSalary->id);
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'تم حذف البدل بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function getSalaryArchive($id)
    {
        try {
            $user = auth()->user();
            $company_id = $user->company_id;

            $employee = Employee::where(['company_id' => $company_id, 'id' => $id])->first();
            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], 404);
            }

            $data = \App\Models\Employee_salary_archive::with('added')
                ->where(['company_id' => $company_id, 'employee_id' => $id])
                ->orderBy('id', 'DESC')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function addFile(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $employee = Employee::where(['company_id' => $user->company_id, 'id' => $id])->first();
            if (!$employee) return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], 404);

            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'file_path' => 'required|file|mimes:png,jpg,jpeg,pdf,doc,docx|max:5000'
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $dataToInsert = [
                'employee_id' => $id,
                'name' => $request->name,
                'company_id' => $user->company_id,
                'added_by' => $user->id,
            ];

            if ($request->hasFile('file_path')) {
                $file = $request->file('file_path');
                $filename = time() . '_' . uniqid() . '_file.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $dataToInsert['file_path'] = $filename;
            }

            Employee_File::create($dataToInsert);

            return response()->json(['status' => true, 'message' => __('messages.employee_file_added')]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function deleteFile($file_id)
    {
        try {
            $user = auth()->user();
            $data = Employee_File::where(['id' => $file_id, 'company_id' => $user->company_id])->first();
            if (!$data) return response()->json(['status' => false, 'message' => 'غير موجود'], 404);

            if ($data->file_path && file_exists(public_path('assets/admin/uploads/' . $data->file_path))) {
                unlink(public_path('assets/admin/uploads/' . $data->file_path));
            }

            $data->delete();
            return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }
    public function toggleLogin($id)
    {
        try {
            $user = auth()->user();
            $employee = Employee::where(['id' => $id, 'company_id' => $user->company_id])->first();

            if (!$employee) {
                return response()->json(['status' => false, 'message' => __('messages.employee_not_found')], 404);
            }

            // Check if this is the manager's own account
            if ($user->employee_id == $employee->id) {
                return response()->json(['status' => false, 'message' => __('messages.employee_cannot_disable_self')], 422);
            }

            $employeeUser = User::where('employee_id', $employee->id)->where('company_id', $user->company_id)->first();

            if (!$employeeUser) {
                return response()->json(['status' => false, 'message' => __('messages.employee_no_login_account')], 404);
            }

            $employeeUser->is_active = $employeeUser->is_active ? 0 : 1;
            $employeeUser->save();

            if (!$employeeUser->is_active) {
                $employeeUser->tokens()->delete();
            }


            return response()->json([
                'status' => true,
                'message' => $employeeUser->is_active ? __('messages.employee_login_activated') : __('messages.employee_login_deactivated'),
                'is_active' => $employeeUser->is_active
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function resetPassword(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $employee = Employee::where(['id' => $id, 'company_id' => $user->company_id])->first();

            if (!$employee) {
                return response()->json(['status' => false, 'message' => __('messages.employee_not_found')], 404);
            }

            $employeeUser = User::where('employee_id', $employee->id)->where('company_id', $user->company_id)->first();

            if (!$employeeUser) {
                return response()->json(['status' => false, 'message' => __('messages.employee_no_login_account')], 404);
            }

            $validator = Validator::make($request->all(), [
                'password' => 'nullable|string|min:6|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $newPassword = $request->filled('password')
                ? $request->password
                : (string) random_int(10000000, 99999999);

            $employeeUser->password = Hash::make($newPassword);
            $employeeUser->initial_password = $newPassword;
            $employeeUser->save();

            return response()->json([
                'status' => true,
                'message' => __('messages.employee_password_reset_success'),
                'initial_password' => $newPassword,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }
}
