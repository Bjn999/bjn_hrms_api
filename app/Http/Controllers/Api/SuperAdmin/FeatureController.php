<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeatureController extends Controller
{
    private function checkSuperAdmin()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(response()->json(['status' => false, 'message' => __('messages.unauthorized')], 403));
        }
    }

    public function index()
    {
        $this->checkSuperAdmin();

        $features = Feature::orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();

        return response()->json([
            'status' => true,
            'data' => $features
        ]);
    }

    public function store(Request $request)
    {
        $this->checkSuperAdmin();

        $request->validate([
            'name' => 'required|string|max:190',
            'code' => 'nullable|string|max:100|unique:features,code',
            'type' => 'required|in:boolean,number,text',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'default_value' => 'nullable|string|max:190',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            $code = $request->code ? Str::slug($request->code, '_') : Str::slug($request->name, '_');
            $originalCode = $code;
            $counter = 1;
            while (Feature::where('code', $code)->exists()) {
                $code = $originalCode . '_' . $counter++;
            }

            $feature = Feature::create([
                'name' => $request->name,
                'code' => $code,
                'type' => $request->type,
                'unit' => $request->unit,
                'description' => $request->description,
                'default_value' => $request->default_value,
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => $request->sort_order ?? 0,
            ]);

            return response()->json([
                'status' => true,
                'message' => __('messages.feature_created'),
                'data' => $feature
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $this->checkSuperAdmin();

        $feature = Feature::find($id);
        if (!$feature) {
            return response()->json(['status' => false, 'message' => __('messages.feature_not_found')], 404);
        }

        $request->validate([
            'name' => 'required|string|max:190',
            'code' => 'nullable|string|max:100|unique:features,code,' . $id,
            'type' => 'required|in:boolean,number,text',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'default_value' => 'nullable|string|max:190',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            $code = $request->code ? Str::slug($request->code, '_') : $feature->code;

            $feature->update([
                'name' => $request->name,
                'code' => $code,
                'type' => $request->type,
                'unit' => $request->unit,
                'description' => $request->description,
                'default_value' => $request->default_value,
                'is_active' => $request->boolean('is_active', true),
                'sort_order' => $request->sort_order ?? $feature->sort_order,
            ]);

            return response()->json([
                'status' => true,
                'message' => __('messages.feature_updated'),
                'data' => $feature
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }

    public function destroy($id)
    {
        $this->checkSuperAdmin();

        $feature = Feature::find($id);
        if (!$feature) {
            return response()->json(['status' => false, 'message' => __('messages.feature_not_found')], 404);
        }

        try {
            $feature->delete();
            return response()->json([
                'status' => true,
                'message' => __('messages.feature_deleted')
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => __('messages.server_error')], 500);
        }
    }
}
