<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
                    ->orWhere('email', $request->username)
                    ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['البيانات المدخلة غير صحيحة'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['هذا الحساب غير مفعل'],
            ]);
        }

        // Delete old tokens
        $user->tokens()->delete();

        // Create token with abilities based on role
        $token = $user->createToken('auth_token', [$user->role])->plainTextToken;
        
        // Load related profile based on role (or fallback to employee_id if present)
        if ($user->employee_id) {
            $user->load('employee');
        }
                
        // If user is a company_admin, we give them all permissions by default in the frontend,
        // If user is super_admin, they don't need these specific company permissions.
        // otherwise we only return their assigned Spatie permissions.
        if ($user->role === 'company_admin') {
            $permissions = Permission::pluck('name');
        } else if ($user->role === 'super_admin') {
            $permissions = collect([]);
        } else {
            $permissions = $user->getAllPermissions()->pluck('name');
            
            // Fallback: If no Spatie roles are attached, but user has legacy string role 'employee'
            // Map it to the Arabic role name created in the UI
            if ($permissions->isEmpty() && $user->role === 'employee') {
                $employeeRole = Role::where('name', 'موظف')
                    ->where('company_id', $user->company_id)
                    ->first();
                
                if (!$employeeRole) {
                    $employeeRole = Role::where('name', 'موظف')->first();
                }

                if ($employeeRole) {
                    $permissions = $employeeRole->permissions->pluck('name');
                }
            }
        }

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
            $user->load('employee');
        }
        
        return response()->json([
            'status' => true,
            'data' => $user
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
            $user->load('employee');
        }

        $message = $credentialsChanged 
            ? __('messages.credentials_updated') 
            : __('messages.profile_updated');

        return response()->json([
            'status' => true,
            'message' => $message,
            'credentials_changed' => $credentialsChanged,
            'data' => $user
        ]);
    }
}
