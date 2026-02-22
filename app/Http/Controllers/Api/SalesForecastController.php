<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\SalesForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesForecastController extends Controller
{
    protected SalesForecastService $forecastService;

    public function __construct(SalesForecastService $forecastService)
    {
        $this->forecastService = $forecastService;
    }

    /**
     * Get 7-day sales forecast.
     */
    public function forecast(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $days = $request->input('days', 7);

        $result = $this->forecastService
            ->forTenant($tenantId)
            ->getForecast(min($days, 14)); // Max 14 days

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Get busy hours analysis.
     */
    public function busyHours(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $result = $this->forecastService
            ->forTenant($tenantId)
            ->getBusyHours();

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Get staffing recommendations.
     */
    public function staffing(): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $result = $this->forecastService
            ->forTenant($tenantId)
            ->getStaffingRecommendations();

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }
}
