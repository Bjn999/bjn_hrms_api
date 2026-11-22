<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionStatus
{
    /**
     * Whitelist of route names or URL segments accessible even when subscription is expired.
     */
    protected array $allowedSegments = [
        'company-subscription',
        'subscription-upgrade-requests',
        'plans',
        'logout',
        'notifications',
        'profile',
        'generalSettings',
        'broadcasting/auth',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            $user->currentAccessToken()?->delete();
            return response()->json([
                'status' => false,
                'message' => __('messages.account_disabled')
            ], 403);
        }

        // Bypass check for unauthenticated, super admins, or users without company
        if (!$user || $user->role === 'super_admin' || !$user->company_id) {
            return $next($request);
        }

        $status = SubscriptionService::getCompanySubscriptionStatus($user->company_id);

        // If subscription is expired (Services paused)
        if ($status['is_expired']) {
            if ($request->is('api/user')) {
                return $next($request);
            }

            // Check if current route is in the whitelist (e.g. renewing subscription or viewing subscription status)
            foreach ($this->allowedSegments as $segment) {
                if (str_contains($request->path(), $segment)) {
                    return $next($request);
                }
            }

            // Revoke current access token
            $user->currentAccessToken()?->delete();

            return response()->json([
                'status' => false,
                'code' => 'SUBSCRIPTION_SUSPENDED',
                'message' => __('messages.subscription_expired_access_denied'),
                'is_expired' => true,
                'contact_support' => true,
            ], 403);
        }

        return $next($request);
    }
}
