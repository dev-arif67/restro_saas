<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->tenant_id) {
            return response()->json(['message' => 'No tenant associated with this user.'], 403);
        }

        if (!$user->tenant || !$user->tenant->is_active) {
            return response()->json(['message' => 'Tenant account is inactive.'], 403);
        }

        return $next($request);
    }
}
