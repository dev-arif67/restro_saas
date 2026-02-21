<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Settlement;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseApiController
{
    public function salesReport(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $from = $request->from;
        $to = $request->to;

        $orders = Order::completed()->dateRange($from, $to);

        $totalSales = (clone $orders)->sum('grand_total');
        $totalOrders = (clone $orders)->count();
        $totalTax = (clone $orders)->sum('vat_amount');
        $totalDiscount = (clone $orders)->sum('discount');
        $totalNetAmount = (clone $orders)->sum('net_amount');
        $avgOrderValue = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0;

        $dineInSales = (clone $orders)->dineIn()->sum('grand_total');
        $parcelSales = (clone $orders)->parcel()->sum('grand_total');

        $dailyBreakdown = Order::completed()
            ->dateRange($from, $to)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('SUM(vat_amount) as vat'),
                DB::raw('SUM(net_amount) as net_amount'),
                DB::raw('SUM(discount) as discounts')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success([
            'period' => ['from' => $from, 'to' => $to],
            'summary' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'total_vat' => $totalTax,
                'total_net_amount' => $totalNetAmount,
                'total_discount' => $totalDiscount,
                'avg_order_value' => $avgOrderValue,
                'dine_in_sales' => $dineInSales,
                'parcel_sales' => $parcelSales,
            ],
            'daily_breakdown' => $dailyBreakdown,
        ]);
    }

    public function voucherReport(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $voucherImpact = Order::completed()
            ->dateRange($request->from, $request->to)
            ->whereNotNull('voucher_id')
            ->select(
                'voucher_id',
                DB::raw('COUNT(*) as times_used'),
                DB::raw('SUM(discount) as total_discount'),
                DB::raw('SUM(grand_total) as total_revenue')
            )
            ->groupBy('voucher_id')
            ->with('voucher:id,code,type,discount_value')
            ->get();

        $totalDiscountGiven = $voucherImpact->sum('total_discount');
        $totalOrdersWithVoucher = $voucherImpact->sum('times_used');

        return $this->success([
            'total_discount_given' => $totalDiscountGiven,
            'total_orders_with_voucher' => $totalOrdersWithVoucher,
            'voucher_breakdown' => $voucherImpact,
        ]);
    }

    public function tablePerformance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $tableStats = Order::completed()
            ->dineIn()
            ->dateRange($request->from, $request->to)
            ->whereNotNull('table_id')
            ->select(
                'table_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(grand_total) as total_revenue'),
                DB::raw('AVG(grand_total) as avg_order_value')
            )
            ->groupBy('table_id')
            ->with('table:id,table_number')
            ->orderByDesc('total_revenue')
            ->get();

        return $this->success($tableStats);
    }

    public function trendReport(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $groupBy = match ($request->period) {
            'daily' => 'DATE(created_at)',
            'weekly' => 'YEARWEEK(created_at)',
            'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        $trends = Order::completed()
            ->dateRange($request->from, $request->to)
            ->select(
                DB::raw("{$groupBy} as period"),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('AVG(grand_total) as avg_order_value')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->success($trends);
    }

    public function topSellingItems(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $limit = $request->get('limit', 10);

        $topItems = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.created_at', [$request->from, $request->to])
            ->select(
                'order_items.menu_item_id',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('SUM(order_items.line_total) as total_revenue')
            )
            ->groupBy('order_items.menu_item_id')
            ->with('menuItem:id,name,price')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get();

        return $this->success($topItems);
    }

    public function revenueComparison(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        $currentYear = now()->year;
        $lastYear = $currentYear - 1;

        $monthlyRevenue = function ($year) use ($tenantId) {
            return Order::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereYear('created_at', $year)
                ->select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(grand_total) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->keyBy('month');
        };

        return $this->success([
            'current_year' => [
                'year' => $currentYear,
                'months' => $monthlyRevenue($currentYear),
            ],
            'last_year' => [
                'year' => $lastYear,
                'months' => $monthlyRevenue($lastYear),
            ],
        ]);
    }

    public function settlementReport(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $settlements = Settlement::where('tenant_id', $tenantId)
            ->with('payments')
            ->latest()
            ->get();

        $totalSold = $settlements->sum('total_sold');
        $totalCommission = $settlements->sum('commission_amount');
        $totalPaid = $settlements->sum('total_paid');
        $totalPayable = $settlements->sum('payable_balance');

        return $this->success([
            'summary' => [
                'total_sold' => $totalSold,
                'total_commission' => $totalCommission,
                'total_paid' => $totalPaid,
                'total_payable' => $totalPayable,
            ],
            'settlements' => $settlements,
        ]);
    }
}
