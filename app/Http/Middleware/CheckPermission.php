<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => __('messages.unauthorized')
            ], 401);
        }

        // Super Admin & Company Admin are granted full access via Gate::before
        if ($user->role === 'super_admin' || $user->role === 'company_admin') {
            return $next($request);
        }

        // Support multiple permissions separated by pipe '|' (OR logic) or comma ',' (AND logic)
        $permissions = explode('|', $permission);
        $hasPermission = false;

        foreach ($permissions as $perm) {
            $perm = trim($perm);
            if (Gate::allows($perm) || $user->hasPermissionTo($perm)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return response()->json([
                'status' => false,
                'message' => __('messages.unauthorized_permission')
            ], 403);
        }

        return $next($request);
    }
}
