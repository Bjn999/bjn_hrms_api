<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Additional_sal_type;
use App\Models\Main_salary_employee_addition;
use App\Models\Main_salary_employee_reward;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdditionalSalTypesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $company_id = auth()->user()->company_id;
            $data = Additional_sal_type::with(['added:id,name', 'updatedby:id,name'])
                ->where('company_id', $company_id)
                ->orderBy('id', 'DESC')
                ->get();
            
            foreach ($data as $info) {
                $counterReward = Main_salary_employee_reward::where(['company_id' => $company_id, 'additions_type_id' => $info->id])->count();
                $info->counterUsed = $counterReward;
            }
            
            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Additional_sal_type::where(['company_id' => $user->company_id, 'name' => $request->name])->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => __('messages.data_exists')], 422);
            }

            Additional_sal_type::create([
                'name' => $request->name,
                'active' => $request->active,
                'company_id' => $user->company_id,
                'added_by' => $user->id,
            ]);

            return response()->json(['status' => true, 'message' => __('messages.saved_successfully')]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $data = Additional_sal_type::where(['id' => $id, 'company_id' => $user->company_id])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = Additional_sal_type::where(['company_id' => $user->company_id, 'name' => $request->name])->where('id', '!=', $id)->first();
            if ($checkExist) {
                return response()->json(['status' => false, 'message' => __('messages.data_exists')], 422);
            }

            $data->update([
                'name' => $request->name,
                'active' => $request->active,
                'updated_by' => $user->id,
            ]);

            return response()->json(['status' => true, 'message' => __('messages.updated_successfully')]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $data = Additional_sal_type::where(['id' => $id, 'company_id' => $user->company_id])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
            }

            $counterReward = Main_salary_employee_reward::where(['company_id' => $user->company_id, 'additions_type_id' => $id])->count();
            $counterAddition = Main_salary_employee_addition::where(['company_id' => $user->company_id, 'additions_type_id' => $id])->count();
            if (($counterReward + $counterAddition) > 0) {
                return response()->json(['status' => false, 'message' => __('messages.cannot_delete_used')], 422);
            }

            $data->delete();
            return response()->json(['status' => true, 'message' => __('messages.deleted_successfully')]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }
}

