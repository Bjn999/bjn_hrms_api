<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admins;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = Admins::where('username', $request->username)->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'username' => ['البيانات المدخلة غير صحيحة'],
            ]);
        }

        // Delete old tokens if you want only one active session (optional)
        // $admin->tokens()->delete();

        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'admin' => $admin,
                'token' => $token,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'status' => true,
            'data' => $request->user()
        ]);
    }
}
