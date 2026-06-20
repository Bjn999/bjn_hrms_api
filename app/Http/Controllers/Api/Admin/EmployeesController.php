<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
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
use App\Traits\GeneralTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EmployeesController extends Controller
{
    use GeneralTrait;
    public function index(Request $request)
    {
        try {
            $com_code = auth()->user()->com_code;
            $query = Employee::with(['branch:id,name', 'department:id,name', 'job:id,name', 'added:id,name', 'updatedby:id,name'])
                ->where('com_code', $com_code);
            
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

            foreach ($data as $info) {
                $info->counterUsed = Main_salary_employee::where(['com_code' => $com_code, 'employee_code' => $info->employee_code])->count();
            }
            
            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function get_required_data(Request $request)
    {
        try {
            $com_code = auth()->user()->com_code;
            $data = [
                'branches' => Branche::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'departments' => Department::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'jobs' => Jobs_categories::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'qualifications' => Qualification::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'blood_groups' => Blood_Group::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'nationalities' => Nationality::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'religions' => Religion::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'countries' => Country::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'governorates' => Governorate::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name', 'country_id']),
                'cities' => Center::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name', 'governorate_id']),
                'shifts' => shifts_type::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'type', 'from_time', 'to_time', 'total_hour']),
                'resignations' => Resignation::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'languages' => Language::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'social_status' => Social_Status_Type::where(['active' => 1])->get(['id', 'name']),
                'military_status' => Military_Status::where(['active' => 1])->get(['id', 'name']),
                'driving_license_types' => Driving_license_type::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
                'allowances' => Allowance::where(['com_code' => $com_code, 'active' => 1])->get(['id', 'name']),
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
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Employee::where(['com_code' => $user->com_code, 'emp_name' => $request->emp_name])->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'اسم الموظف مسجل من قبل'], 422);
            }

            if ($request->zketo_code) {
                $checkZketo = Employee::where(['com_code' => $user->com_code, 'zketo_code' => $request->zketo_code])->first();
                if ($checkZketo) {
                    return response()->json(['status' => false, 'message' => 'كود البصمة مسجل لموظف آخر'], 422);
                }
            }

            $last_employee = Employee::where('com_code', $user->com_code)->orderBy('employee_code', 'DESC')->first();
            $employee_code = $last_employee ? $last_employee->employee_code + 1 : 1;

            $dataToInsert = $request->except(['emp_photo', 'emp_cv']);
            $dataToInsert['employee_code'] = $employee_code;
            $dataToInsert['com_code'] = $user->com_code;
            $dataToInsert['added_by'] = $user->id;
            $dataToInsert['date'] = date('Y-m-d');

            if ($request->hasFile('emp_photo')) {
                $file = $request->file('emp_photo');
                $filename = time() . '_photo.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $dataToInsert['emp_photo'] = $filename;
            }

            if ($request->hasFile('emp_cv')) {
                $file = $request->file('emp_cv');
                $filename = time() . '_cv.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $dataToInsert['emp_cv'] = $filename;
            }

            $employee = Employee::create($dataToInsert);
            
            \App\Models\Employee_salary_archive::create([
                'employee_id' => $employee->id,
                'value' => $employee->emp_sal,
                'added_by' => $user->id,
                'com_code' => $user->com_code
            ]);

            return response()->json(['status' => true, 'message' => 'تم الحفظ بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $user = auth()->user();
            $data = Employee::with([
                'branch', 'department', 'job', 'qualification', 'blood_group', 
                'nationality', 'religion', 'country', 'governorate', 'city', 'shift',
                'language', 'social_status', 'resignation', 'added:id,name', 'updatedby:id,name',
                'fixedAllowances.allowance', 'files'
            ])->where(['id' => $id, 'com_code' => $user->com_code])->first();

            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $data = Employee::where(['id' => $id, 'com_code' => $user->com_code])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $validator = Validator::make($request->all(), [
                'emp_name' => 'required',
                'branch_id' => 'required',
                'emp_departments_id' => 'required',
                'emp_job_id' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Employee::where(['com_code' => $user->com_code, 'emp_name' => $request->emp_name])->where('id', '!=', $id)->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => 'اسم الموظف مسجل من قبل'], 422);
            }

            $requestData = $request->except(['employee_code', 'com_code', 'added_by', 'date', 'emp_photo', 'emp_cv']);
            $requestData['updated_by'] = $user->id;
            
            if ($request->hasFile('emp_photo')) {
                if ($data->emp_photo && file_exists(public_path('assets/admin/uploads/' . $data->emp_photo))) {
                    unlink(public_path('assets/admin/uploads/' . $data->emp_photo));
                }
                $file = $request->file('emp_photo');
                $filename = time() . '_photo.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $requestData['emp_photo'] = $filename;
            }

            if ($request->hasFile('emp_cv')) {
                if ($data->emp_cv && file_exists(public_path('assets/admin/uploads/' . $data->emp_cv))) {
                    unlink(public_path('assets/admin/uploads/' . $data->emp_cv));
                }
                $file = $request->file('emp_cv');
                $filename = time() . '_cv.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $requestData['emp_cv'] = $filename;
            }

            $old_salary = $data->emp_sal;
            $data->update($requestData);
            
            if ($old_salary != $data->emp_sal) {
                \App\Models\Employee_salary_archive::create([
                    'employee_id' => $data->id,
                    'value' => $data->emp_sal,
                    'added_by' => $user->id,
                    'com_code' => $user->com_code
                ]);
            }

            if (isset($requestData['does_has_fixed_allowance']) && $requestData['does_has_fixed_allowance'] == 0) {
                Employee_fixed_allowance::where(['com_code' => $user->com_code, 'employee_id' => $id])->delete();
            }

            // Recalculate main salary if there is an active/open payroll for this employee
            $currentSalary = Main_salary_employee::where(['com_code' => $user->com_code, 'employee_code' => $data->employee_code, 'is_archived' => 0])->first();
            if ($currentSalary) {
                $this->recaculate_main_salary_employee($currentSalary->id);
            }

            return response()->json(['status' => true, 'message' => 'تم التعديل بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $data = Employee::where(['id' => $id, 'com_code' => $user->com_code])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => 'غير موجود'], 404);
            }

            $counterUsed = Main_salary_employee::where(['com_code' => $user->com_code, 'employee_code' => $data->employee_code])->count();
            if ($counterUsed > 0) {
                return response()->json(['status' => false, 'message' => 'عفواً لا يمكن حذف الموظف لأنه لديه سجلات رواتب في النظام'], 422);
            }
            
            $data->delete();
            return response()->json(['status' => true, 'message' => 'تم الحذف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function addFixedAllowance(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $com_code = $user->com_code;

            $employee = Employee::where(['com_code' => $com_code, 'id' => $id])->first();
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
                'com_code' => $com_code,
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
                'com_code' => $com_code,
            ];

            $flag = Employee_fixed_allowance::create($dataToInsert);

            if ($flag) {
                // لو يوجد راتب مفتوح للموظف نعيد احتسابه
                $currentSalary = Main_salary_employee::where(['com_code' => $com_code, 'employee_code' => $employee->employee_code, 'is_archived' => 0])->first();
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
            $com_code = $user->com_code;

            $fixedAllowance = \App\Models\Employee_fixed_allowance::where(['com_code' => $com_code, 'id' => $id])->first();
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

            $employee = \App\Models\Employee::where(['com_code' => $com_code, 'id' => $fixedAllowance->employee_id])->first();
            if ($employee) {
                $currentSalary = \App\Models\Main_salary_employee::where(['com_code' => $com_code, 'employee_code' => $employee->employee_code, 'is_archived' => 0])->first();
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
            $com_code = $user->com_code;

            $fixedAllowance = Employee_fixed_allowance::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$fixedAllowance) {
                return response()->json(['status' => false, 'message' => 'البدل غير موجود'], 404);
            }

            $employee = Employee::where(['com_code' => $com_code, 'id' => $fixedAllowance->employee_id])->first();

            $fixedAllowance->delete();

            if ($employee) {
                // لو يوجد راتب مفتوح للموظف نعيد احتسابه
                $currentSalary = Main_salary_employee::where(['com_code' => $com_code, 'employee_code' => $employee->employee_code, 'is_archived' => 0])->first();
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
            $com_code = $user->com_code;

            $employee = Employee::where(['com_code' => $com_code, 'id' => $id])->first();
            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'الموظف غير موجود'], 404);
            }

            $data = \App\Models\Employee_salary_archive::with('added')
                ->where(['com_code' => $com_code, 'employee_id' => $id])
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
            $employee = Employee::where(['com_code' => $user->com_code, 'id' => $id])->first();
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
                'com_code' => $user->com_code,
                'added_by' => $user->id,
            ];

            if ($request->hasFile('file_path')) {
                $file = $request->file('file_path');
                $filename = time() . '_file.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/admin/uploads'), $filename);
                $dataToInsert['file_path'] = $filename;
            }

            Employee_File::create($dataToInsert);

            return response()->json(['status' => true, 'message' => 'تم إضافة الملف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()], 500);
        }
    }

    public function deleteFile($file_id)
    {
        try {
            $user = auth()->user();
            $data = Employee_File::where(['id' => $file_id, 'com_code' => $user->com_code])->first();
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
}
