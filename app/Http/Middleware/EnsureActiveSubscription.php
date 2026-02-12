<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'No tenant found.'], 403);
        }

        if ($tenant->isSubscriptionExpired()) {
            return response()->json([
                'message' => 'Subscription expired. Please renew to continue.',
                'subscription_expired' => true,
            ], 402);
        }

        return $next($request);
    }
}
