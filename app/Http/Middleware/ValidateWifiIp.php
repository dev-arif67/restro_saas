<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWifiIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantParam = $request->route('tenant');

        if (!$tenantParam) {
            return $next($request);
        }

        $tenant = is_numeric($tenantParam)
            ? Tenant::find($tenantParam)
            : Tenant::where('slug', $tenantParam)->first();

        if (!$tenant) {
            return response()->json(['message' => 'Restaurant not found.'], 404);
        }

        if (!$tenant->isWifiEnforced()) {
            return $next($request);
        }

        $clientIp = $request->ip();

        $authorizedIps = array_map('trim', explode(',', $tenant->authorized_wifi_ip));

        if (!in_array($clientIp, $authorizedIps)) {
            return response()->json([
                'message' => 'You must be connected to the restaurant\'s WiFi to place an order.',
                'wifi_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
