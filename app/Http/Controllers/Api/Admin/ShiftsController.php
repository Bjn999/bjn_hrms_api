<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\shifts_type;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ShiftsController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();
            $data = shifts_type::with(['added:id,name', 'updatedby:id,name'])
                ->where("company_id", $user->company_id)
                ->orderBy('id', 'DESC')
                ->get();
            
            foreach ($data as $item) {
                $item->counterUsed = Employee::where(['company_id' => $user->company_id, 'shift_type_id' => $item->id])->count();
            }
            
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
                'type' => 'required',
                'from_time' => 'required',
                'to_time' => 'required',
                'total_hour' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = shifts_type::where([
                'company_id' => $user->company_id,
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour
            ])->first();

            if ($checkExist) {
                return response()->json(['status' => false, 'message' => __('messages.data_exists')], 422);
            }

            shifts_type::create([
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour,
                'active' => $request->active,
                'company_id' => $user->company_id,
                'added_by' => $user->id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return response()->json(['status' => true, 'message' => __('messages.saved_successfully')]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $data = shifts_type::where(['id' => $id, 'company_id' => $user->company_id])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
            }

            $validator = Validator::make($request->all(), [
                'type' => 'required',
                'from_time' => 'required',
                'to_time' => 'required',
                'total_hour' => 'required',
                'active' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $checkExist = shifts_type::where([
                'company_id' => $user->company_id,
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour
            ])->where('id', '!=', $id)->first();

            if ($checkExist) {
                return response()->json(['status' => false, 'message' => __('messages.data_exists')], 422);
            }

            $counterUsed = Employee::where(['company_id' => $user->company_id, 'shift_type_id' => $id])->count();
            if ($counterUsed > 0 && $request->active == 0) {
                return response()->json(['status' => false, 'message' => __('messages.cannot_disable_used')], 422);
            }

            $data->update([
                'type' => $request->type,
                'from_time' => $request->from_time,
                'to_time' => $request->to_time,
                'total_hour' => $request->total_hour,
                'active' => $request->active,
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return response()->json(['status' => true, 'message' => __('messages.updated_successfully')]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $data = shifts_type::where(['id' => $id, 'company_id' => $user->company_id])->first();
            if (!$data) {
                return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
            }

            $counterUsed = Employee::where(['company_id' => $user->company_id, 'shift_type_id' => $id])->count();
            if ($counterUsed > 0) {
                return response()->json(['status' => false, 'message' => __('messages.cannot_delete_used')], 422);
            }

            $data->delete();
            return response()->json(['status' => true, 'message' => __('messages.deleted_successfully')]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
