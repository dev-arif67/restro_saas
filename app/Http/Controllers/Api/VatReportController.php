<?php

namespace App\Http\Controllers\Api;

use App\Services\VatReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VatReportController extends BaseApiController
{
    public function __construct(
        protected VatReportService $reportService,
    ) {}

    /**
     * Daily Z Report — per restaurant per day.
     *
     * GET /api/reports/vat/daily?date=2026-02-21
     */
    public function dailyZReport(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $report = $this->reportService->dailyZReport($tenantId, $request->date);

        return $this->success($report, 'Daily Z Report');
    }

    /**
     * Monthly VAT Report — for date range.
     *
     * GET /api/reports/vat/monthly?from=2026-02-01&to=2026-02-28
     */
    public function monthlyVatReport(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);

        $tenantId = auth()->user()->tenant_id;

        $report = $this->reportService->monthlyVatReport($tenantId, $request->from, $request->to);

        return $this->success($report, 'Monthly VAT Report');
    }
}
