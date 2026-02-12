<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return $this->superAdminDashboard();
        }

        return $this->tenantDashboard($user->tenant_id);
    }

    private function superAdminDashboard(): JsonResponse
    {
        return $this->success([
            'tenants' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('is_active', true)->count(),
            ],
            'users' => User::count(),
            'orders_today' => Order::withoutGlobalScopes()->whereDate('created_at', today())->count(),
            'revenue_today' => Order::withoutGlobalScopes()
                ->whereDate('created_at', today())
                ->where('status', 'completed')
                ->sum('grand_total'),
            'recent_tenants' => Tenant::latest()->limit(5)->get(['id', 'name', 'created_at']),
        ]);
    }

    private function tenantDashboard(int $tenantId): JsonResponse
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $ordersToday = Order::where('created_at', '>=', $today);
        $ordersMonth = Order::where('created_at', '>=', $thisMonth);

        $revenueToday = (clone $ordersToday)->completed()->sum('grand_total');
        $revenueMonth = (clone $ordersMonth)->completed()->sum('grand_total');

        return $this->success([
            'orders' => [
                'today' => (clone $ordersToday)->count(),
                'this_month' => (clone $ordersMonth)->count(),
                'pending' => Order::whereIn('status', ['placed', 'confirmed'])->count(),
                'preparing' => Order::where('status', 'preparing')->count(),
                'ready' => Order::where('status', 'ready')->count(),
            ],
            'revenue' => [
                'today' => $revenueToday,
                'this_month' => $revenueMonth,
            ],
            'recent_orders' => Order::with(['table', 'items.menuItem'])
                ->latest()
                ->limit(10)
                ->get(),
            'top_items' => DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
                ->where('orders.tenant_id', $tenantId)
                ->where('orders.status', 'completed')
                ->where('orders.created_at', '>=', $thisMonth)
                ->select(
                    'menu_items.name',
                    DB::raw('SUM(order_items.qty) as total_qty'),
                    DB::raw('SUM(order_items.line_total) as total_revenue')
                )
                ->groupBy('menu_items.name')
                ->orderByDesc('total_qty')
                ->limit(5)
                ->get(),
        ]);
    }
}
