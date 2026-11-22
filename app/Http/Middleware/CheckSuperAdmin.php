<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    /**
     * Ensure the authenticated user has the 'super_admin' role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => __('messages.unauthorized')
            ], 401);
        }

        if ($user->role !== 'super_admin') {
            return response()->json([
                'status' => false,
                'message' => __('messages.unauthorized_permission')
            ], 403);
        }

        return $next($request);
    }
}

