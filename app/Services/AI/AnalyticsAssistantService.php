<?php

namespace App\Services\AI;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RestaurantTable;
use App\Models\Voucher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsAssistantService
{
    protected GeminiService $gemini;
    protected ?int $tenantId;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Set the tenant context for queries.
     */
    public function forTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Process a natural language analytics query.
     */
    public function ask(string $question, array $conversationHistory = []): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        // First, determine what data is needed based on the question
        $intent = $this->classifyIntent($question);

        // Fetch relevant data based on intent
        $data = $this->fetchDataForIntent($intent, $question);

        // Build context with the data
        $dataContext = $this->formatDataContext($intent, $data);

        // Get system prompt
        $systemPrompt = config('ai.prompts.analytics_assistant');

        // Build the full prompt
        $fullPrompt = $this->buildAnalyticsPrompt($question, $dataContext);

        // Get AI response
        $response = $this->gemini->chat(
            $fullPrompt,
            $systemPrompt,
            $conversationHistory,
            $this->tenantId,
            'analytics_assistant'
        );

        if (!$response['success']) {
            return $response;
        }

        // Parse response for any chart data
        $chartData = $this->extractChartData($response['content'], $intent, $data);

        return [
            'success' => true,
            'answer' => $response['content'],
            'chart' => $chartData,
            'intent' => $intent,
            'cached' => $response['cached'] ?? false,
        ];
    }

    /**
     * Get proactive insights for the dashboard.
     */
    public function getInsights(): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        // Gather key metrics
        $todayStats = $this->getTodayStats();
        $weekComparison = $this->getWeekComparison();
        $topItems = $this->getTopSellingItems(7, 5);
        $slowItems = $this->getSlowMovingItems(7, 3);

        // Build insight prompt
        $prompt = $this->buildInsightPrompt($todayStats, $weekComparison, $topItems, $slowItems);

        $systemPrompt = <<<'PROMPT'
You are a concise business insights assistant. Generate 3-4 brief, actionable insights based on the restaurant data provided.

Format each insight as:
- **Title**: One sentence insight
- Keep insights practical and specific
- Include numbers where relevant (use ৳ for currency)
- Focus on trends, opportunities, and potential issues
PROMPT;

        $response = $this->gemini->chat(
            $prompt,
            $systemPrompt,
            [],
            $this->tenantId,
            'analytics_insights'
        );

        if (!$response['success']) {
            return $response;
        }

        return [
            'success' => true,
            'insights' => $response['content'],
            'metrics' => [
                'today' => $todayStats,
                'week_comparison' => $weekComparison,
            ],
            'cached' => $response['cached'] ?? false,
        ];
    }

    /**
     * Classify the intent of the user's question.
     */
    protected function classifyIntent(string $question): string
    {
        $question = strtolower($question);

        // Revenue/Sales patterns
        if (preg_match('/\b(revenue|sales|income|earning|money|total|gross)\b/', $question)) {
            if (preg_match('/\b(compare|comparison|vs|versus|difference)\b/', $question)) {
                return 'revenue_comparison';
            }
            if (preg_match('/\b(trend|over time|daily|weekly|monthly|growth)\b/', $question)) {
                return 'revenue_trend';
            }
            return 'revenue_summary';
        }

        // Order patterns
        if (preg_match('/\b(order|orders)\b/', $question)) {
            if (preg_match('/\b(status|pending|preparing|ready)\b/', $question)) {
                return 'order_status';
            }
            if (preg_match('/\b(average|avg|mean)\b/', $question)) {
                return 'order_average';
            }
            return 'order_summary';
        }

        // Menu/Item patterns
        if (preg_match('/\b(best|top|popular|selling|item|menu|dish|product)\b/', $question)) {
            if (preg_match('/\b(slow|worst|least|unpopular|not selling)\b/', $question)) {
                return 'slow_items';
            }
            return 'top_items';
        }

        // Category patterns
        if (preg_match('/\b(category|categories)\b/', $question)) {
            return 'category_performance';
        }

        // Table patterns
        if (preg_match('/\b(table|tables|seating)\b/', $question)) {
            return 'table_performance';
        }

        // Time patterns
        if (preg_match('/\b(hour|hourly|peak|busy|rush|time)\b/', $question)) {
            return 'hourly_analysis';
        }

        // Voucher/Discount patterns
        if (preg_match('/\b(voucher|coupon|discount|promo)\b/', $question)) {
            return 'voucher_analysis';
        }

        // Customer patterns
        if (preg_match('/\b(customer|dine.?in|parcel|takeaway|delivery)\b/', $question)) {
            return 'customer_analysis';
        }

        // Default to general summary
        return 'general_summary';
    }

    /**
     * Fetch data based on the classified intent.
     */
    protected function fetchDataForIntent(string $intent, string $question): array
    {
        // Parse time range from question
        $dateRange = $this->parseDateRange($question);

        return match ($intent) {
            'revenue_summary' => $this->getRevenueSummary($dateRange),
            'revenue_comparison' => $this->getRevenueComparison($dateRange),
            'revenue_trend' => $this->getRevenueTrend($dateRange),
            'order_summary' => $this->getOrderSummary($dateRange),
            'order_status' => $this->getOrderStatusBreakdown(),
            'order_average' => $this->getOrderAverages($dateRange),
            'top_items' => $this->getTopSellingItems($dateRange['days'], 10),
            'slow_items' => $this->getSlowMovingItems($dateRange['days'], 10),
            'category_performance' => $this->getCategoryPerformance($dateRange),
            'table_performance' => $this->getTablePerformance($dateRange),
            'hourly_analysis' => $this->getHourlyAnalysis($dateRange),
            'voucher_analysis' => $this->getVoucherAnalysis($dateRange),
            'customer_analysis' => $this->getCustomerAnalysis($dateRange),
            default => $this->getGeneralSummary($dateRange),
        };
    }

    /**
     * Parse date range from natural language.
     */
    protected function parseDateRange(string $question): array
    {
        $question = strtolower($question);
        $now = Carbon::now();

        // Today
        if (preg_match('/\btoday\b/', $question)) {
            return ['from' => $now->copy()->startOfDay(), 'to' => $now, 'days' => 1, 'label' => 'today'];
        }

        // Yesterday
        if (preg_match('/\byesterday\b/', $question)) {
            return [
                'from' => $now->copy()->subDay()->startOfDay(),
                'to' => $now->copy()->subDay()->endOfDay(),
                'days' => 1,
                'label' => 'yesterday'
            ];
        }

        // This week
        if (preg_match('/\bthis week\b/', $question)) {
            return ['from' => $now->copy()->startOfWeek(), 'to' => $now, 'days' => 7, 'label' => 'this week'];
        }

        // Last week
        if (preg_match('/\blast week\b/', $question)) {
            return [
                'from' => $now->copy()->subWeek()->startOfWeek(),
                'to' => $now->copy()->subWeek()->endOfWeek(),
                'days' => 7,
                'label' => 'last week'
            ];
        }

        // This month
        if (preg_match('/\bthis month\b/', $question)) {
            return ['from' => $now->copy()->startOfMonth(), 'to' => $now, 'days' => 30, 'label' => 'this month'];
        }

        // Last month
        if (preg_match('/\blast month\b/', $question)) {
            return [
                'from' => $now->copy()->subMonth()->startOfMonth(),
                'to' => $now->copy()->subMonth()->endOfMonth(),
                'days' => 30,
                'label' => 'last month'
            ];
        }

        // Last N days
        if (preg_match('/\blast (\d+) days?\b/', $question, $matches)) {
            $days = (int) $matches[1];
            return [
                'from' => $now->copy()->subDays($days)->startOfDay(),
                'to' => $now,
                'days' => $days,
                'label' => "last {$days} days"
            ];
        }

        // Default to last 7 days
        return ['from' => $now->copy()->subDays(7)->startOfDay(), 'to' => $now, 'days' => 7, 'label' => 'last 7 days'];
    }

    /**
     * Get revenue summary for a date range.
     */
    protected function getRevenueSummary(array $dateRange): array
    {
        $orders = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        return [
            'period' => $dateRange['label'],
            'total_revenue' => (float) (clone $orders)->sum('grand_total'),
            'total_orders' => (clone $orders)->count(),
            'total_vat' => (float) (clone $orders)->sum('vat_amount'),
            'total_discount' => (float) (clone $orders)->sum('discount'),
            'avg_order_value' => (float) (clone $orders)->avg('grand_total') ?? 0,
            'dine_in_revenue' => (float) (clone $orders)->where('type', 'dine_in')->sum('grand_total'),
            'parcel_revenue' => (float) (clone $orders)->where('type', 'parcel')->sum('grand_total'),
        ];
    }

    /**
     * Get revenue comparison (current vs previous period).
     */
    protected function getRevenueComparison(array $dateRange): array
    {
        $days = $dateRange['days'];

        // Current period
        $current = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        // Previous period
        $prevFrom = $dateRange['from']->copy()->subDays($days);
        $prevTo = $dateRange['from']->copy()->subSecond();

        $previous = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$prevFrom, $prevTo]);

        $currentRevenue = (float) $current->sum('grand_total');
        $previousRevenue = (float) $previous->sum('grand_total');
        $change = $previousRevenue > 0
            ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : 0;

        return [
            'current_period' => $dateRange['label'],
            'current_revenue' => $currentRevenue,
            'current_orders' => $current->count(),
            'previous_revenue' => $previousRevenue,
            'previous_orders' => $previous->count(),
            'revenue_change_percent' => $change,
            'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
        ];
    }

    /**
     * Get revenue trend over time.
     */
    protected function getRevenueTrend(array $dateRange): array
    {
        $daily = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'period' => $dateRange['label'],
            'daily_data' => $daily->toArray(),
            'total_days' => $daily->count(),
            'avg_daily_revenue' => round($daily->avg('revenue') ?? 0, 2),
        ];
    }

    /**
     * Get order summary.
     */
    protected function getOrderSummary(array $dateRange): array
    {
        $orders = Order::where('tenant_id', $this->tenantId)
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        return [
            'period' => $dateRange['label'],
            'total_orders' => (clone $orders)->count(),
            'completed' => (clone $orders)->where('status', 'completed')->count(),
            'cancelled' => (clone $orders)->where('status', 'cancelled')->count(),
            'dine_in' => (clone $orders)->where('type', 'dine_in')->count(),
            'parcel' => (clone $orders)->where('type', 'parcel')->count(),
            'from_pos' => (clone $orders)->where('source', 'pos')->count(),
            'from_customer' => (clone $orders)->where('source', 'customer')->count(),
        ];
    }

    /**
     * Get current order status breakdown.
     */
    protected function getOrderStatusBreakdown(): array
    {
        $today = Carbon::today();

        return Order::where('tenant_id', $this->tenantId)
            ->whereDate('created_at', $today)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get order averages.
     */
    protected function getOrderAverages(array $dateRange): array
    {
        $orders = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        $dailyOrders = (clone $orders)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as orders'))
            ->groupBy('date')
            ->get();

        return [
            'period' => $dateRange['label'],
            'avg_order_value' => round((clone $orders)->avg('grand_total') ?? 0, 2),
            'avg_items_per_order' => round(
                OrderItem::whereIn('order_id', (clone $orders)->pluck('id'))->avg('qty') ?? 0,
                1
            ),
            'avg_orders_per_day' => round($dailyOrders->avg('orders') ?? 0, 1),
        ];
    }

    /**
     * Get top selling items.
     */
    protected function getTopSellingItems(int $days, int $limit = 10): array
    {
        $from = Carbon::now()->subDays($days)->startOfDay();

        return OrderItem::whereHas('order', function ($q) use ($from) {
            $q->where('tenant_id', $this->tenantId)
                ->completed()
                ->where('created_at', '>=', $from);
        })
            ->select(
                'menu_item_id',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('SUM(line_total) as total_revenue')
            )
            ->groupBy('menu_item_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->with('menuItem:id,name,price')
            ->get()
            ->map(fn($item) => [
                'name' => $item->menuItem->name ?? 'Unknown',
                'quantity_sold' => (int) $item->total_qty,
                'revenue' => (float) $item->total_revenue,
            ])
            ->toArray();
    }

    /**
     * Get slow moving items.
     */
    protected function getSlowMovingItems(int $days, int $limit = 10): array
    {
        $from = Carbon::now()->subDays($days)->startOfDay();

        // Get items that have been sold
        $soldItemIds = OrderItem::whereHas('order', function ($q) use ($from) {
            $q->where('tenant_id', $this->tenantId)
                ->completed()
                ->where('created_at', '>=', $from);
        })->pluck('menu_item_id')->unique();

        // Get active menu items not sold or sold very little
        return MenuItem::where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereNotIn('id', $soldItemIds)
            ->limit($limit)
            ->get(['id', 'name', 'price'])
            ->map(fn($item) => [
                'name' => $item->name,
                'price' => (float) $item->price,
                'last_sold' => 'Not sold in period',
            ])
            ->toArray();
    }

    /**
     * Get category performance.
     */
    protected function getCategoryPerformance(array $dateRange): array
    {
        return OrderItem::whereHas('order', function ($q) use ($dateRange) {
            $q->where('tenant_id', $this->tenantId)
                ->completed()
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);
        })
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('categories', 'menu_items.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category',
                DB::raw('SUM(order_items.qty) as items_sold'),
                DB::raw('SUM(order_items.line_total) as revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }

    /**
     * Get table performance.
     */
    protected function getTablePerformance(array $dateRange): array
    {
        return Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->where('type', 'dine_in')
            ->whereNotNull('table_id')
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->select(
                'table_id',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('AVG(grand_total) as avg_order')
            )
            ->groupBy('table_id')
            ->with('table:id,table_number')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn($row) => [
                'table' => $row->table->table_number ?? 'Unknown',
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
                'avg_order' => round($row->avg_order, 2),
            ])
            ->toArray();
    }

    /**
     * Get hourly analysis.
     */
    protected function getHourlyAnalysis(array $dateRange): array
    {
        return Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn($row) => [
                'hour' => sprintf('%02d:00', $row->hour),
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ])
            ->toArray();
    }

    /**
     * Get voucher analysis.
     */
    protected function getVoucherAnalysis(array $dateRange): array
    {
        $orders = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        $withVoucher = (clone $orders)->whereNotNull('voucher_id');
        $totalDiscount = (float) $withVoucher->sum('discount');

        return [
            'period' => $dateRange['label'],
            'orders_with_voucher' => $withVoucher->count(),
            'total_orders' => $orders->count(),
            'total_discount_given' => $totalDiscount,
            'voucher_usage' => Order::where('tenant_id', $this->tenantId)
                ->completed()
                ->whereNotNull('voucher_id')
                ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']])
                ->select('voucher_id', DB::raw('COUNT(*) as uses'), DB::raw('SUM(discount) as discount'))
                ->groupBy('voucher_id')
                ->with('voucher:id,code')
                ->get()
                ->map(fn($row) => [
                    'code' => $row->voucher->code ?? 'Unknown',
                    'uses' => (int) $row->uses,
                    'discount' => (float) $row->discount,
                ])
                ->toArray(),
        ];
    }

    /**
     * Get customer analysis (dine-in vs parcel).
     */
    protected function getCustomerAnalysis(array $dateRange): array
    {
        $orders = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$dateRange['from'], $dateRange['to']]);

        return [
            'period' => $dateRange['label'],
            'dine_in' => [
                'orders' => (clone $orders)->where('type', 'dine_in')->count(),
                'revenue' => (float) (clone $orders)->where('type', 'dine_in')->sum('grand_total'),
            ],
            'parcel' => [
                'orders' => (clone $orders)->where('type', 'parcel')->count(),
                'revenue' => (float) (clone $orders)->where('type', 'parcel')->sum('grand_total'),
            ],
            'by_source' => [
                'pos' => (clone $orders)->where('source', 'pos')->count(),
                'customer_qr' => (clone $orders)->where('source', 'customer')->count(),
            ],
        ];
    }

    /**
     * Get general summary.
     */
    protected function getGeneralSummary(array $dateRange): array
    {
        return [
            'revenue' => $this->getRevenueSummary($dateRange),
            'top_items' => $this->getTopSellingItems($dateRange['days'], 5),
            'orders' => $this->getOrderSummary($dateRange),
        ];
    }

    /**
     * Get today's stats.
     */
    protected function getTodayStats(): array
    {
        $today = Carbon::today();

        $orders = Order::where('tenant_id', $this->tenantId)
            ->whereDate('created_at', $today);

        return [
            'revenue' => (float) (clone $orders)->completed()->sum('grand_total'),
            'orders' => (clone $orders)->count(),
            'completed' => (clone $orders)->where('status', 'completed')->count(),
            'pending' => (clone $orders)->whereIn('status', ['placed', 'confirmed', 'preparing', 'ready'])->count(),
        ];
    }

    /**
     * Get week over week comparison.
     */
    protected function getWeekComparison(): array
    {
        $thisWeek = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->sum('grand_total');

        $lastWeek = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek()
            ])
            ->sum('grand_total');

        $change = $lastWeek > 0 ? round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1) : 0;

        return [
            'this_week' => (float) $thisWeek,
            'last_week' => (float) $lastWeek,
            'change_percent' => $change,
        ];
    }

    /**
     * Format data context for AI prompt.
     */
    protected function formatDataContext(string $intent, array $data): string
    {
        return "Data for analysis:\n" . json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Build the analytics prompt.
     */
    protected function buildAnalyticsPrompt(string $question, string $dataContext): string
    {
        return <<<PROMPT
User Question: {$question}

{$dataContext}

Please analyze this data and provide a clear, helpful response to the user's question.
Use ৳ for currency values. Keep the response concise but informative.
PROMPT;
    }

    /**
     * Build insight prompt.
     */
    protected function buildInsightPrompt(array $today, array $weekComparison, array $topItems, array $slowItems): string
    {
        return <<<PROMPT
Restaurant Performance Data:

Today's Stats:
- Revenue: ৳{$today['revenue']}
- Total Orders: {$today['orders']}
- Completed: {$today['completed']}
- Pending: {$today['pending']}

Week Comparison:
- This Week: ৳{$weekComparison['this_week']}
- Last Week: ৳{$weekComparison['last_week']}
- Change: {$weekComparison['change_percent']}%

Top Selling Items (Last 7 Days):
{$this->formatItemsList($topItems)}

Items Not Selling Well:
{$this->formatItemsList($slowItems)}

Generate 3-4 brief, actionable business insights based on this data.
PROMPT;
    }

    /**
     * Format items list for prompt.
     */
    protected function formatItemsList(array $items): string
    {
        if (empty($items)) {
            return "No data available";
        }

        return collect($items)->map(function ($item, $i) {
            $revenue = isset($item['revenue']) ? " - ৳" . number_format($item['revenue'], 0) : '';
            return ($i + 1) . ". {$item['name']}{$revenue}";
        })->join("\n");
    }

    /**
     * Extract chart data from response based on intent.
     */
    protected function extractChartData(string $response, string $intent, array $data): ?array
    {
        // Return chart-friendly data for certain intents
        return match ($intent) {
            'revenue_trend' => [
                'type' => 'line',
                'title' => 'Revenue Trend',
                'data' => $data['daily_data'] ?? [],
            ],
            'top_items' => [
                'type' => 'bar',
                'title' => 'Top Selling Items',
                'data' => array_slice($data, 0, 5),
            ],
            'hourly_analysis' => [
                'type' => 'bar',
                'title' => 'Orders by Hour',
                'data' => $data,
            ],
            'category_performance' => [
                'type' => 'pie',
                'title' => 'Revenue by Category',
                'data' => $data,
            ],
            default => null,
        };
    }
}
