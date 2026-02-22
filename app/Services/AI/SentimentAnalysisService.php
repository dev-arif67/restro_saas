<?php

namespace App\Services\AI;

use App\Models\Order;
use App\Models\AIConversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SentimentAnalysisService
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
     * Analyze sentiment of a single text.
     */
    public function analyzeSentiment(string $text): array
    {
        if (empty(trim($text))) {
            return ['success' => false, 'error' => 'No text provided'];
        }

        // Try rule-based first for common patterns
        $ruleBased = $this->ruleBasedSentiment($text);
        if ($ruleBased['confidence'] >= 0.8) {
            return [
                'success' => true,
                'sentiment' => $ruleBased['sentiment'],
                'score' => $ruleBased['score'],
                'confidence' => $ruleBased['confidence'],
                'method' => 'rule_based',
            ];
        }

        // Use AI for complex cases
        if (!$this->gemini->isConfigured()) {
            return [
                'success' => true,
                'sentiment' => $ruleBased['sentiment'],
                'score' => $ruleBased['score'],
                'confidence' => $ruleBased['confidence'],
                'method' => 'rule_based',
            ];
        }

        return $this->aiSentimentAnalysis($text);
    }

    /**
     * Get sentiment overview for a time period.
     */
    public function getSentimentOverview(string $period = '30d'): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        $cacheKey = "sentiment_overview:{$this->tenantId}:{$period}";

        return Cache::remember($cacheKey, 1800, function () use ($period) {
            $startDate = $this->parseStartDate($period);

            // Get order notes
            $orderNotes = Order::where('tenant_id', $this->tenantId)
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->pluck('notes')
                ->toArray();

            // Get customer chat messages
            $chatMessages = AIConversation::where('tenant_id', $this->tenantId)
                ->where('type', 'customer_chat')
                ->where('created_at', '>=', $startDate)
                ->get()
                ->flatMap(function ($conv) {
                    $messages = $conv->messages ?? [];
                    return collect($messages)
                        ->filter(fn($m) => ($m['role'] ?? '') === 'user')
                        ->pluck('content');
                })
                ->toArray();

            $allTexts = array_merge($orderNotes, $chatMessages);

            if (empty($allTexts)) {
                return [
                    'success' => true,
                    'summary' => [
                        'total_analyzed' => 0,
                        'positive' => 0,
                        'neutral' => 0,
                        'negative' => 0,
                        'average_score' => 0,
                    ],
                    'insights' => 'No customer feedback found for this period.',
                    'trends' => [],
                    'common_themes' => [],
                ];
            }

            // Analyze each text
            $results = [];
            foreach ($allTexts as $text) {
                if (strlen(trim($text)) < 5) continue;
                $analysis = $this->analyzeSentiment($text);
                if ($analysis['success']) {
                    $results[] = $analysis;
                }
            }

            if (empty($results)) {
                return [
                    'success' => true,
                    'summary' => [
                        'total_analyzed' => 0,
                        'positive' => 0,
                        'neutral' => 0,
                        'negative' => 0,
                        'average_score' => 0,
                    ],
                    'insights' => 'No analyzable feedback found.',
                    'trends' => [],
                    'common_themes' => [],
                ];
            }

            // Calculate summary
            $positive = count(array_filter($results, fn($r) => $r['sentiment'] === 'positive'));
            $negative = count(array_filter($results, fn($r) => $r['sentiment'] === 'negative'));
            $neutral = count(array_filter($results, fn($r) => $r['sentiment'] === 'neutral'));
            $avgScore = array_sum(array_column($results, 'score')) / count($results);

            // Extract common themes
            $themes = $this->extractThemes($allTexts);

            // Generate AI insights if available
            $insights = $this->generateInsights($results, $themes);

            return [
                'success' => true,
                'summary' => [
                    'total_analyzed' => count($results),
                    'positive' => $positive,
                    'neutral' => $neutral,
                    'negative' => $negative,
                    'positive_percent' => round(($positive / count($results)) * 100),
                    'negative_percent' => round(($negative / count($results)) * 100,),
                    'average_score' => round($avgScore, 2),
                ],
                'insights' => $insights,
                'common_themes' => $themes,
                'period' => $period,
            ];
        });
    }

    /**
     * Get daily sentiment trends.
     */
    public function getSentimentTrends(int $days = 14): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        $cacheKey = "sentiment_trends:{$this->tenantId}:{$days}";

        return Cache::remember($cacheKey, 3600, function () use ($days) {
            $trends = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');
                $startOfDay = Carbon::now()->subDays($i)->startOfDay();
                $endOfDay = Carbon::now()->subDays($i)->endOfDay();

                // Get notes for this day
                $dayNotes = Order::where('tenant_id', $this->tenantId)
                    ->whereBetween('created_at', [$startOfDay, $endOfDay])
                    ->whereNotNull('notes')
                    ->where('notes', '!=', '')
                    ->pluck('notes')
                    ->toArray();

                $positive = 0;
                $negative = 0;
                $neutral = 0;
                $scores = [];

                foreach ($dayNotes as $note) {
                    $analysis = $this->analyzeSentiment($note);
                    if ($analysis['success']) {
                        $scores[] = $analysis['score'];
                        match ($analysis['sentiment']) {
                            'positive' => $positive++,
                            'negative' => $negative++,
                            default => $neutral++,
                        };
                    }
                }

                $trends[] = [
                    'date' => $date,
                    'total' => count($dayNotes),
                    'positive' => $positive,
                    'negative' => $negative,
                    'neutral' => $neutral,
                    'avg_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null,
                ];
            }

            return [
                'success' => true,
                'trends' => $trends,
                'days' => $days,
            ];
        });
    }

    /**
     * Get negative feedback for attention.
     */
    public function getNegativeFeedback(int $limit = 10): array
    {
        if (!$this->tenantId) {
            return ['success' => false, 'error' => 'Tenant context required'];
        }

        $recentOrders = Order::where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->with('items.menuItem:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $negativeFeedback = [];

        foreach ($recentOrders as $order) {
            $analysis = $this->analyzeSentiment($order->notes);
            if ($analysis['success'] && $analysis['sentiment'] === 'negative') {
                $negativeFeedback[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'date' => $order->created_at->format('Y-m-d H:i'),
                    'feedback' => $order->notes,
                    'score' => $analysis['score'],
                    'items' => $order->items->map(fn($i) => $i->menuItem->name ?? 'Unknown')->toArray(),
                ];
            }

            if (count($negativeFeedback) >= $limit) break;
        }

        return [
            'success' => true,
            'feedback' => $negativeFeedback,
            'total' => count($negativeFeedback),
        ];
    }

    /**
     * Rule-based sentiment analysis.
     */
    protected function ruleBasedSentiment(string $text): array
    {
        $text = strtolower($text);

        $positiveWords = [
            'excellent', 'amazing', 'great', 'good', 'delicious', 'tasty', 'wonderful',
            'fantastic', 'love', 'loved', 'best', 'perfect', 'fresh', 'yummy', 'awesome',
            'superb', 'outstanding', 'brilliant', 'satisfied', 'happy', 'thank', 'thanks',
            'beautiful', 'nice', 'friendly', 'quick', 'fast', 'recommended', 'favorite',
            'ভালো', 'সুন্দর', 'অসাধারণ', 'চমৎকার', 'মজা', 'ধন্যবাদ', 'খুশি',
        ];

        $negativeWords = [
            'bad', 'terrible', 'awful', 'horrible', 'worst', 'disgusting', 'poor',
            'cold', 'slow', 'late', 'wrong', 'mistake', 'disappointed', 'disappointing',
            'rude', 'dirty', 'expensive', 'overpriced', 'stale', 'raw', 'undercooked',
            'overcooked', 'burnt', 'missing', 'forgot', 'never', 'hate', 'angry', 'upset',
            'refund', 'complaint', 'complain', 'waste', 'gross', 'sick',
            'খারাপ', 'বাজে', 'নষ্ট', 'দেরি', 'ঠান্ডা', 'মানহীন',
        ];

        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($positiveWords as $word) {
            if (str_contains($text, $word)) $positiveCount++;
        }

        foreach ($negativeWords as $word) {
            if (str_contains($text, $word)) $negativeCount++;
        }

        // Check for negations
        $negations = ['not', 'no', "n't", 'never', 'নয়', 'না'];
        $hasNegation = false;
        foreach ($negations as $neg) {
            if (str_contains($text, $neg)) {
                $hasNegation = true;
                break;
            }
        }

        // Swap if negation detected (simple approach)
        if ($hasNegation && $positiveCount > $negativeCount) {
            [$positiveCount, $negativeCount] = [$negativeCount, $positiveCount];
        }

        $total = $positiveCount + $negativeCount;
        if ($total === 0) {
            return [
                'sentiment' => 'neutral',
                'score' => 0.5,
                'confidence' => 0.3,
            ];
        }

        $score = ($positiveCount - $negativeCount + $total) / (2 * $total);
        $score = max(0, min(1, $score));

        $confidence = min(0.9, 0.4 + ($total * 0.1));

        if ($score >= 0.6) {
            $sentiment = 'positive';
        } elseif ($score <= 0.4) {
            $sentiment = 'negative';
        } else {
            $sentiment = 'neutral';
        }

        return [
            'sentiment' => $sentiment,
            'score' => round($score, 2),
            'confidence' => round($confidence, 2),
        ];
    }

    /**
     * AI-powered sentiment analysis.
     */
    protected function aiSentimentAnalysis(string $text): array
    {
        $prompt = <<<PROMPT
Analyze the sentiment of this customer feedback:

"{$text}"

Return ONLY a JSON object with these fields:
- sentiment: "positive", "negative", or "neutral"
- score: number from 0 to 1 (0 = very negative, 1 = very positive)
- confidence: number from 0 to 1
- keywords: array of up to 3 key emotional words from the text

Example: {"sentiment": "positive", "score": 0.85, "confidence": 0.9, "keywords": ["delicious", "fast"]}
PROMPT;

        $response = $this->gemini->chatJson(
            $prompt,
            'You are a sentiment analysis expert. Analyze customer feedback accurately. Return only valid JSON.',
            $this->tenantId,
            'sentiment_analysis'
        );

        if (!$response['success'] || empty($response['data'])) {
            return $this->ruleBasedSentiment($text);
        }

        $data = $response['data'];

        return [
            'success' => true,
            'sentiment' => $data['sentiment'] ?? 'neutral',
            'score' => (float) ($data['score'] ?? 0.5),
            'confidence' => (float) ($data['confidence'] ?? 0.7),
            'keywords' => $data['keywords'] ?? [],
            'method' => 'ai',
        ];
    }

    /**
     * Extract common themes from texts.
     */
    protected function extractThemes(array $texts): array
    {
        $wordCounts = [];
        $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'must', 'shall', 'can', 'need', 'to', 'of', 'in', 'for', 'on',
            'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after',
            'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once', 'here',
            'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most',
            'other', 'some', 'such', 'only', 'own', 'same', 'so', 'than', 'too', 'very',
            'just', 'and', 'but', 'if', 'or', 'because', 'until', 'while', 'although',
            'i', 'me', 'my', 'myself', 'we', 'our', 'you', 'your', 'he', 'she', 'it', 'they',
            'this', 'that', 'these', 'those', 'am', 'please', 'thank', 'thanks', 'want', 'like',
        ];

        foreach ($texts as $text) {
            $words = preg_split('/\s+/', strtolower(preg_replace('/[^a-zA-Z\s]/', '', $text)));
            foreach ($words as $word) {
                if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                    $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                }
            }
        }

        arsort($wordCounts);

        $themes = [];
        $i = 0;
        foreach ($wordCounts as $word => $count) {
            if ($count >= 2 && $i < 10) {
                $themes[] = [
                    'word' => $word,
                    'count' => $count,
                    'sentiment' => $this->ruleBasedSentiment($word)['sentiment'],
                ];
                $i++;
            }
        }

        return $themes;
    }

    /**
     * Generate AI insights from analysis.
     */
    protected function generateInsights(array $results, array $themes): string
    {
        $positive = count(array_filter($results, fn($r) => $r['sentiment'] === 'positive'));
        $negative = count(array_filter($results, fn($r) => $r['sentiment'] === 'negative'));
        $total = count($results);

        if ($total === 0) {
            return 'No feedback to analyze.';
        }

        $positivePercent = round(($positive / $total) * 100);
        $negativePercent = round(($negative / $total) * 100);

        $insights = [];

        // Overall assessment
        if ($positivePercent >= 70) {
            $insights[] = "**Overall Positive**: {$positivePercent}% of feedback is positive. Great job!";
        } elseif ($negativePercent >= 40) {
            $insights[] = "**Attention Needed**: {$negativePercent}% of feedback is negative. Review recent issues.";
        } else {
            $insights[] = "**Mixed Feedback**: {$positivePercent}% positive, {$negativePercent}% negative.";
        }

        // Theme-based insights
        $positiveThemes = array_filter($themes, fn($t) => $t['sentiment'] === 'positive');
        $negativeThemes = array_filter($themes, fn($t) => $t['sentiment'] === 'negative');

        if (!empty($positiveThemes)) {
            $topPositive = array_values($positiveThemes)[0]['word'] ?? '';
            if ($topPositive) {
                $insights[] = "**Strength**: '{$topPositive}' mentioned frequently in positive context.";
            }
        }

        if (!empty($negativeThemes)) {
            $topNegative = array_values($negativeThemes)[0]['word'] ?? '';
            if ($topNegative) {
                $insights[] = "**Concern**: '{$topNegative}' appears in negative feedback. Consider improvements.";
            }
        }

        return implode("\n\n", $insights);
    }

    /**
     * Parse period string to start date.
     */
    protected function parseStartDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => Carbon::now()->subDays(7),
            '14d' => Carbon::now()->subDays(14),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            default => Carbon::now()->subDays(30),
        };
    }
}
