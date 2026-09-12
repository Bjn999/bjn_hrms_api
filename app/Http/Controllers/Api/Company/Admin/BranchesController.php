<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BranchesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $data = Branche::with(['added:id,name', 'updatedby:id,name'])
                ->where("company_id", $user->company_id)
                ->orderBy('id', 'DESC')
                ->get();
            
            foreach ($data as $item) {
                $item->counterUsed = Employee::where(['company_id' => $user->company_id, 'branch_id' => $item->id])->count();
            }
            
            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|unique:branches,name,NULL,id,company_id,'.$user->company_id,
                'address' => 'required|string',
                'phones' => 'required|string',
                'email' => 'nullable|email',
                'active' => 'required|in:0,1',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'geofence_radius' => 'nullable|integer|min:10|max:50000',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

             Branche::create([
                'name' => $request->name,
                'address' => $request->address,
                'phones' => $request->phones,
                'email' => $request->email,
                'active' => $request->active,
                'latitude' => $request->filled('latitude') ? $request->latitude : null,
                'longitude' => $request->filled('longitude') ? $request->longitude : null,
                'geofence_radius' => $request->filled('geofence_radius') ? (int)$request->geofence_radius : 100,
                'added_by' => $user->id,
                'company_id' => $user->company_id,
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
            $user = $request->user();
            $data = Branche::where(['id' => $id, 'company_id' => $user->company_id])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', Rule::unique('branches')->where('company_id', $user->company_id)->ignore($id)],
                'address' => 'required|string',
                'phones' => 'required|string',
                'email' => 'nullable|email',
                'active' => 'required|in:0,1',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'geofence_radius' => 'nullable|integer|min:10|max:50000',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }

            $counterUsed = Employee::where(['company_id' => $user->company_id, 'branch_id' => $id])->count();
            if ($counterUsed > 0 && $request->active == 0) {
                return response()->json(['status' => false, 'message' => __('messages.cannot_disable_used')], 422);
            }

            $data->update([
                'name' => $request->name,
                'address' => $request->address,
                'phones' => $request->phones,
                'email' => $request->email,
                'active' => $request->active,
                'latitude' => $request->filled('latitude') ? $request->latitude : null,
                'longitude' => $request->filled('longitude') ? $request->longitude : null,
                'geofence_radius' => $request->filled('geofence_radius') ? (int)$request->geofence_radius : ($data->geofence_radius ?? 100),
                'updated_by' => $user->id,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return response()->json(['status' => true, 'message' => __('messages.updated_successfully')]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $data = Branche::where(['id' => $id, 'company_id' => $user->company_id])->first();
            
            if (!$data) {
                return response()->json(['status' => false, 'message' => __('messages.not_found')], 404);
            }

            $counterUsed = Employee::where(['company_id' => $user->company_id, 'branch_id' => $id])->count();
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
