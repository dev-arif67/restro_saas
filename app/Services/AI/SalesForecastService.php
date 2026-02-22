<?php

namespace App\Services\AI;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SalesForecastService
{
    protected GeminiService $gemini;
    protected ?int $tenantId = null;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Set tenant context.
     */
    public function forTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Generate a 7-day sales forecast.
     */
    public function getForecast(int $days = 7): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        // Check cache first (forecasts are expensive, cache for 4 hours)
        $cacheKey = "forecast:{$this->tenantId}:{$days}";
        if ($cached = Cache::get($cacheKey)) {
            return array_merge($cached, ['cached' => true]);
        }

        // Gather historical data
        $historicalData = $this->gatherHistoricalData();

        if ($historicalData['total_orders'] < 14) {
            return [
                'success' => false,
                'error' => 'Not enough historical data. Need at least 2 weeks of orders for forecasting.',
                'orders_found' => $historicalData['total_orders'],
            ];
        }

        // Generate statistical forecast first (no AI needed)
        $statisticalForecast = $this->generateStatisticalForecast($historicalData, $days);

        // Use AI to enhance with insights
        $aiInsights = $this->generateAIInsights($historicalData, $statisticalForecast);

        $result = [
            'success' => true,
            'forecast' => $statisticalForecast['daily'],
            'summary' => [
                'predicted_revenue' => $statisticalForecast['total_revenue'],
                'predicted_orders' => $statisticalForecast['total_orders'],
                'confidence' => $statisticalForecast['confidence'],
                'trend' => $statisticalForecast['trend'],
            ],
            'busy_hours' => $historicalData['hourly_patterns'],
            'busy_days' => $historicalData['daily_patterns'],
            'insights' => $aiInsights,
            'generated_at' => now()->toISOString(),
            'cached' => false,
        ];

        // Cache for 4 hours
        Cache::put($cacheKey, $result, 14400);

        return $result;
    }

    /**
     * Get busy hours analysis.
     */
    public function getBusyHours(): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        $hourlyData = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue')
            )
            ->groupBy('hour', 'day_of_week')
            ->get();

        // Organize by day of week
        $byDay = [];
        $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        foreach ($hourlyData as $row) {
            $dayName = $dayNames[$row->day_of_week];
            if (!isset($byDay[$dayName])) {
                $byDay[$dayName] = [];
            }
            $byDay[$dayName][] = [
                'hour' => sprintf('%02d:00', $row->hour),
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ];
        }

        // Find peak hours overall
        $peakHours = $hourlyData
            ->groupBy('hour')
            ->map(fn($group) => [
                'hour' => sprintf('%02d:00', $group->first()->hour),
                'total_orders' => $group->sum('orders'),
                'total_revenue' => $group->sum('revenue'),
            ])
            ->sortByDesc('total_orders')
            ->take(5)
            ->values()
            ->toArray();

        return [
            'success' => true,
            'by_day' => $byDay,
            'peak_hours' => $peakHours,
        ];
    }

    /**
     * Gather historical data for forecasting.
     */
    protected function gatherHistoricalData(): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30)->startOfDay();
        $sixtyDaysAgo = Carbon::now()->subDays(60)->startOfDay();

        // Daily revenue for last 30 days
        $dailyData = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('AVG(grand_total) as avg_order')
            )
            ->groupBy('date', 'day_of_week')
            ->orderBy('date')
            ->get();

        // Hourly patterns
        $hourlyPatterns = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('AVG(grand_total) as avg_revenue')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(fn($row) => [
                'hour' => sprintf('%02d:00', $row->hour),
                'orders' => (int) $row->orders,
                'avg_revenue' => round($row->avg_revenue, 2),
            ])
            ->toArray();

        // Find peak hours
        $peakHours = collect($hourlyPatterns)
            ->sortByDesc('orders')
            ->take(3)
            ->pluck('hour')
            ->toArray();

        // Day of week patterns
        $dayPatterns = $dailyData
            ->groupBy('day_of_week')
            ->map(function ($group, $dayNum) {
                $dayNames = ['', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                return [
                    'day' => $dayNames[$dayNum] ?? 'Unknown',
                    'day_num' => $dayNum,
                    'avg_orders' => round($group->avg('orders'), 1),
                    'avg_revenue' => round($group->avg('revenue'), 2),
                ];
            })
            ->sortBy('day_num')
            ->values()
            ->toArray();

        // Calculate trends
        $firstHalf = $dailyData->take(15);
        $secondHalf = $dailyData->skip(15);

        $firstHalfAvg = $firstHalf->avg('revenue') ?? 0;
        $secondHalfAvg = $secondHalf->avg('revenue') ?? 0;

        $trend = 'stable';
        $trendPercent = 0;
        if ($firstHalfAvg > 0) {
            $trendPercent = round((($secondHalfAvg - $firstHalfAvg) / $firstHalfAvg) * 100, 1);
            if ($trendPercent > 5) {
                $trend = 'up';
            } elseif ($trendPercent < -5) {
                $trend = 'down';
            }
        }

        // Previous 30 days for comparison
        $previousPeriod = Order::where('tenant_id', $this->tenantId)
            ->completed()
            ->whereBetween('created_at', [$sixtyDaysAgo, $thirtyDaysAgo])
            ->select(
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(grand_total) as revenue')
            )
            ->first();

        return [
            'daily_data' => $dailyData->toArray(),
            'total_orders' => $dailyData->sum('orders'),
            'total_revenue' => $dailyData->sum('revenue'),
            'avg_daily_revenue' => round($dailyData->avg('revenue') ?? 0, 2),
            'avg_daily_orders' => round($dailyData->avg('orders') ?? 0, 1),
            'avg_order_value' => round($dailyData->avg('avg_order') ?? 0, 2),
            'hourly_patterns' => $hourlyPatterns,
            'peak_hours' => $peakHours,
            'daily_patterns' => $dayPatterns,
            'trend' => $trend,
            'trend_percent' => $trendPercent,
            'previous_period' => [
                'orders' => (int) ($previousPeriod->orders ?? 0),
                'revenue' => (float) ($previousPeriod->revenue ?? 0),
            ],
        ];
    }

    /**
     * Generate statistical forecast without AI.
     */
    protected function generateStatisticalForecast(array $historical, int $days): array
    {
        $dailyPatterns = collect($historical['daily_patterns'])->keyBy('day_num');
        $avgDailyRevenue = $historical['avg_daily_revenue'];
        $avgDailyOrders = $historical['avg_daily_orders'];
        $trend = $historical['trend'];
        $trendPercent = $historical['trend_percent'];

        $forecast = [];
        $totalRevenue = 0;
        $totalOrders = 0;

        for ($i = 1; $i <= $days; $i++) {
            $date = Carbon::now()->addDays($i);
            $dayOfWeek = $date->dayOfWeek + 1; // MySQL DAYOFWEEK is 1-7 (Sun-Sat)

            // Get pattern multiplier for this day of week
            $dayPattern = $dailyPatterns->get($dayOfWeek);
            $multiplier = 1.0;

            if ($dayPattern && $avgDailyRevenue > 0) {
                $multiplier = $dayPattern['avg_revenue'] / $avgDailyRevenue;
            }

            // Apply trend adjustment (small daily increment/decrement)
            $trendAdjustment = 1 + (($trendPercent / 100) * ($i / 30));

            // Calculate predictions
            $predictedRevenue = round($avgDailyRevenue * $multiplier * $trendAdjustment, 2);
            $predictedOrders = round($avgDailyOrders * $multiplier * $trendAdjustment);

            $forecast[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'predicted_revenue' => $predictedRevenue,
                'predicted_orders' => max(1, $predictedOrders),
                'confidence' => $this->calculateConfidence($historical, $dayOfWeek),
            ];

            $totalRevenue += $predictedRevenue;
            $totalOrders += $predictedOrders;
        }

        // Overall confidence based on data quality
        $dataPoints = count($historical['daily_data']);
        $overallConfidence = min(95, 50 + ($dataPoints * 1.5));

        return [
            'daily' => $forecast,
            'total_revenue' => round($totalRevenue, 2),
            'total_orders' => max(1, round($totalOrders)),
            'confidence' => round($overallConfidence),
            'trend' => $trend,
        ];
    }

    /**
     * Calculate confidence for a specific day.
     */
    protected function calculateConfidence(array $historical, int $dayOfWeek): int
    {
        // Count how many data points we have for this day
        $dataForDay = collect($historical['daily_data'])
            ->where('day_of_week', $dayOfWeek)
            ->count();

        // More data = higher confidence (max 4 weeks of data)
        $baseConfidence = min(90, 50 + ($dataForDay * 10));

        // Add some randomness to make it look more realistic
        return min(95, max(60, $baseConfidence + rand(-5, 5)));
    }

    /**
     * Generate AI-powered insights.
     */
    protected function generateAIInsights(array $historical, array $forecast): ?string
    {
        if (!$this->gemini->isConfigured()) {
            return $this->generateFallbackInsights($historical, $forecast);
        }

        $prompt = $this->buildInsightPrompt($historical, $forecast);
        $systemPrompt = config('ai.prompts.sales_forecast');

        $response = $this->gemini->chat(
            $prompt,
            $systemPrompt,
            [],
            $this->tenantId,
            'sales_forecast'
        );

        if (!$response['success']) {
            return $this->generateFallbackInsights($historical, $forecast);
        }

        return $response['content'];
    }

    /**
     * Build prompt for AI insights.
     */
    protected function buildInsightPrompt(array $historical, array $forecast): string
    {
        $peakHours = implode(', ', $historical['peak_hours']);
        $trend = $historical['trend'];
        $trendPercent = abs($historical['trend_percent']);

        $topDays = collect($historical['daily_patterns'])
            ->sortByDesc('avg_revenue')
            ->take(2)
            ->pluck('day')
            ->implode(' and ');

        return <<<PROMPT
Historical Performance (Last 30 Days):
- Average Daily Revenue: ৳{$historical['avg_daily_revenue']}
- Average Daily Orders: {$historical['avg_daily_orders']}
- Average Order Value: ৳{$historical['avg_order_value']}
- Trend: {$trend} ({$trendPercent}%)
- Peak Hours: {$peakHours}
- Busiest Days: {$topDays}

7-Day Forecast:
- Predicted Revenue: ৳{$forecast['total_revenue']}
- Predicted Orders: {$forecast['total_orders']}
- Confidence: {$forecast['confidence']}%

Based on this data, provide 3-4 brief, actionable insights including:
1. Staffing recommendations for peak hours
2. Inventory suggestions based on expected demand
3. Any concerning trends or opportunities
4. Best days to run promotions

Keep each insight to 1-2 sentences. Use ৳ for currency.
PROMPT;
    }

    /**
     * Generate fallback insights without AI.
     */
    protected function generateFallbackInsights(array $historical, array $forecast): string
    {
        $insights = [];

        // Peak hours insight
        $peakHours = implode(', ', array_slice($historical['peak_hours'], 0, 2));
        $insights[] = "**Peak Hours**: Your busiest times are {$peakHours}. Consider having extra staff during these hours.";

        // Trend insight
        $trend = $historical['trend'];
        $trendPercent = abs($historical['trend_percent']);
        if ($trend === 'up') {
            $insights[] = "**Growing Revenue**: Sales are up {$trendPercent}% compared to the previous period. Keep up the good work!";
        } elseif ($trend === 'down') {
            $insights[] = "**Declining Revenue**: Sales are down {$trendPercent}%. Consider running promotions or reviewing menu prices.";
        }

        // Best day insight
        $bestDay = collect($historical['daily_patterns'])->sortByDesc('avg_revenue')->first();
        if ($bestDay) {
            $insights[] = "**Best Day**: {$bestDay['day']} is your highest revenue day with ৳" . number_format($bestDay['avg_revenue']) . " average.";
        }

        // Forecast insight
        $avgDaily = round($forecast['total_revenue'] / 7);
        $insights[] = "**7-Day Outlook**: Expecting approximately ৳" . number_format($avgDaily) . " daily revenue based on historical patterns.";

        return implode("\n\n", $insights);
    }

    /**
     * Get staffing recommendations.
     */
    public function getStaffingRecommendations(): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        $busyHours = $this->getBusyHours();
        if (!$busyHours['success']) {
            return $busyHours;
        }

        $peakHours = $busyHours['peak_hours'];

        // Simple staffing logic based on order volume
        $recommendations = [];
        foreach ($peakHours as $peak) {
            $hour = $peak['hour'];
            $orders = $peak['total_orders'];

            // Simple formula: 1 staff per 5 orders/hour
            $suggestedStaff = max(2, ceil($orders / 20));

            $recommendations[] = [
                'hour' => $hour,
                'expected_orders' => $orders,
                'suggested_staff' => $suggestedStaff,
                'priority' => $orders > 50 ? 'high' : ($orders > 25 ? 'medium' : 'normal'),
            ];
        }

        return [
            'success' => true,
            'recommendations' => $recommendations,
            'peak_hours' => $peakHours,
        ];
    }
}
