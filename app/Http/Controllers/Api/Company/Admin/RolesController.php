<?php

namespace App\Http\Controllers\Api\Company\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    /**
     * Get all available permissions grouped by category
     */
    public function permissionsList()
    {
        $permissions = Permission::all();

        return response()->json([
            'status' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Display roles
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // If super admin, show all global roles or all roles. 
        // We will make roles global for the system.
        $query = Role::with('permissions:id,name');

        if ($user->role !== 'super_admin') {
            $query->whereNotIn('name', ['super_admin', 'company_admin']);
        }

        $roles = $query->get();

        return response()->json([
            'status' => true,
            'data' => $roles
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        // Only super_admin should create roles based on new requirements
        if ($user->role !== 'super_admin') {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بإضافة أدوار'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'display_name' => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string'
        ]);

        DB::beginTransaction();
        try {
            // Create global role (company_id = null)
            $role = Role::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'guard_name' => 'web',
                'company_id' => null 
            ]);

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.created'),
                'data' => $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show single role
     */
    public function show($id)
    {
        $role = Role::with('permissions:id,name')->find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => __('messages.not_found')
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $role
        ]);
    }

    /**
     * Update role
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بتعديل الأدوار'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => __('messages.not_found')
            ], 404);
        }

        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,' . $id,
            'display_name' => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string'
        ]);

        DB::beginTransaction();
        try {
            $role->name = $request->name;
            $role->display_name = $request->display_name;
            $role->save();

            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => __('messages.updated'),
                'data' => $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete role
     */
    public function destroy($id)
    {
        $user = auth()->user();
        if ($user->role !== 'super_admin') {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بحذف الأدوار'], 403);
        }

        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'status' => false,
                'message' => __('messages.not_found')
            ], 404);
        }

        $role->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.deleted')
        ]);
    }

    /**
     * Assign role to user
     */
    public function assignRoleToUser(Request $request, $userId)
    {
        /**@var User $authUser */
        $authUser = auth()->user();

        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        // setPermissionsTeamId($authUser->company_id);

        $targetUser = User::where('id', $userId)
            ->where('company_id', $authUser->company_id)
            ->firstOrFail();

        $role = Role::where('id', $request->role_id)
            ->firstOrFail();

        if (in_array($role->name, ['company_admin', 'super_admin'])) {
            return response()->json([
                'status' => false,
                'message' => __('messages.cannot_assign_company_admin_role')
            ], 422);
        }

        $targetUser->syncRoles([$role]);
        $targetUser->update(['role' => $role->name]);

        return response()->json([
            'status' => true,
            'message' => __('messages.updated_successfully')
        ]);
    }
}
