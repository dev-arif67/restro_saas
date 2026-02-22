<?php

namespace App\Services\AI;

use App\Models\AIUsageLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class GeminiService
{
    protected string $apiKey;
    protected string $model;
    protected string $embeddingModel;
    protected string $baseUrl;
    protected int $timeout;
    protected int $maxTokens;

    public function __construct()
    {
        $config = config('ai.providers.gemini');

        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gemini-3-flash-preview';
        $this->embeddingModel = $config['embedding_model'] ?? 'text-embedding-004';
        $this->baseUrl = $config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';
        $this->timeout = $config['timeout'] ?? 30;
        $this->maxTokens = $config['max_tokens'] ?? 2048;
    }

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Generate a chat completion response.
     *
     * @param string $prompt The user's prompt
     * @param string|null $systemPrompt Optional system-level instructions
     * @param array $context Previous conversation context
     * @param int|null $tenantId Tenant ID for rate limiting
     * @param string $feature Feature name for logging
     * @return array{success: bool, content: string|null, error: string|null, cached: bool}
     */
    public function chat(
        string $prompt,
        ?string $systemPrompt = null,
        array $context = [],
        ?int $tenantId = null,
        string $feature = 'general'
    ): array {
        // Check configuration
        if (!$this->isConfigured()) {
            return $this->errorResponse('AI service is not configured. Please set GEMINI_API_KEY.');
        }

        // Check rate limits
        if (!$this->checkRateLimits($tenantId)) {
            return $this->errorResponse('Rate limit exceeded. Please try again later.', 429);
        }

        // Check cache
        $cacheKey = $this->getCacheKey($prompt, $systemPrompt, $context);
        if (config('ai.cache.enabled') && $cached = Cache::get($cacheKey)) {
            return [
                'success' => true,
                'content' => $cached,
                'error' => null,
                'cached' => true,
            ];
        }

        // Build request contents
        $contents = $this->buildContents($prompt, $systemPrompt, $context);

        try {
            $startTime = microtime(true);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->buildUrl('generateContent'), [
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => $this->maxTokens,
                        'temperature' => 0.7,
                    ],
                    'safetySettings' => $this->getSafetySettings(),
                ]);

            $duration = microtime(true) - $startTime;

            if (!$response->successful()) {
                $error = $response->json('error.message', 'Unknown API error');
                $this->logUsage($tenantId, $feature, 0, false, $error);
                return $this->errorResponse($error, $response->status());
            }

            $data = $response->json();
            $content = $this->extractContent($data);

            if ($content === null) {
                $blockReason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
                return $this->errorResponse("Response blocked: {$blockReason}");
            }

            // Estimate token usage (Gemini doesn't always return this)
            $tokensUsed = $data['usageMetadata']['totalTokenCount'] ?? $this->estimateTokens($prompt . $content);

            // Log usage
            $this->logUsage($tenantId, $feature, $tokensUsed, true);

            // Cache response
            if (config('ai.cache.enabled')) {
                Cache::put($cacheKey, $content, config('ai.cache.ttl', 3600));
            }

            // Increment rate limiter
            $this->incrementRateLimits($tenantId);

            return [
                'success' => true,
                'content' => $content,
                'error' => null,
                'cached' => false,
                'tokens' => $tokensUsed,
                'duration' => round($duration, 3),
            ];

        } catch (\Exception $e) {
            Log::error('GeminiService error', [
                'message' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'feature' => $feature,
            ]);

            $this->logUsage($tenantId, $feature, 0, false, $e->getMessage());

            return $this->errorResponse('AI service temporarily unavailable.');
        }
    }

    /**
     * Generate embeddings for text (for recommendations).
     */
    public function embed(string $text, ?int $tenantId = null): array
    {
        if (!$this->isConfigured()) {
            return $this->errorResponse('AI service is not configured.');
        }

        if (!$this->checkRateLimits($tenantId)) {
            return $this->errorResponse('Rate limit exceeded.', 429);
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/models/{$this->embeddingModel}:embedContent?key={$this->apiKey}", [
                    'model' => "models/{$this->embeddingModel}",
                    'content' => [
                        'parts' => [['text' => $text]],
                    ],
                ]);

            if (!$response->successful()) {
                return $this->errorResponse($response->json('error.message', 'Embedding failed'));
            }

            $embedding = $response->json('embedding.values', []);

            $this->incrementRateLimits($tenantId);
            $this->logUsage($tenantId, 'embedding', $this->estimateTokens($text), true);

            return [
                'success' => true,
                'embedding' => $embedding,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::error('GeminiService embedding error', ['message' => $e->getMessage()]);
            return $this->errorResponse('Embedding generation failed.');
        }
    }

    /**
     * Structured output - parse JSON response.
     */
    public function chatJson(
        string $prompt,
        ?string $systemPrompt = null,
        ?int $tenantId = null,
        string $feature = 'general'
    ): array {
        $result = $this->chat($prompt, $systemPrompt, [], $tenantId, $feature);

        if (!$result['success']) {
            return $result;
        }

        // Try to parse JSON from response
        $content = $result['content'];

        // Extract JSON if wrapped in markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->errorResponse('Failed to parse AI response as JSON.');
        }

        return [
            'success' => true,
            'data' => $decoded,
            'error' => null,
            'cached' => $result['cached'] ?? false,
        ];
    }

    /**
     * Build the API URL.
     */
    protected function buildUrl(string $endpoint): string
    {
        return "{$this->baseUrl}/models/{$this->model}:{$endpoint}?key={$this->apiKey}";
    }

    /**
     * Build contents array for the API request.
     */
    protected function buildContents(string $prompt, ?string $systemPrompt, array $context): array
    {
        $contents = [];

        // Add context from previous conversation
        foreach ($context as $message) {
            $contents[] = [
                'role' => $message['role'] ?? 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        // Add system prompt as a user message (Gemini style)
        if ($systemPrompt) {
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => "System Instructions:\n{$systemPrompt}"]],
            ];
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => 'Understood. I will follow these instructions.']],
            ];
        }

        // Add the actual user prompt
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ];

        return $contents;
    }

    /**
     * Extract content from API response.
     */
    protected function extractContent(array $data): ?string
    {
        $candidates = $data['candidates'] ?? [];

        if (empty($candidates)) {
            return null;
        }

        $content = $candidates[0]['content']['parts'][0]['text'] ?? null;

        return $content;
    }

    /**
     * Get safety settings for the API request.
     */
    protected function getSafetySettings(): array
    {
        return [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
        ];
    }

    /**
     * Check rate limits for tenant.
     */
    protected function checkRateLimits(?int $tenantId): bool
    {
        $limits = config('ai.rate_limits');

        // Check global daily limit
        if (RateLimiter::tooManyAttempts('ai:global:daily', $limits['global_daily_limit'])) {
            return false;
        }

        // Check tenant limits if applicable
        if ($tenantId) {
            if (RateLimiter::tooManyAttempts("ai:tenant:{$tenantId}:daily", $limits['tenant_daily_limit'])) {
                return false;
            }
            if (RateLimiter::tooManyAttempts("ai:tenant:{$tenantId}:hourly", $limits['tenant_hourly_limit'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Increment rate limit counters.
     */
    protected function incrementRateLimits(?int $tenantId): void
    {
        // Global daily (resets at midnight)
        RateLimiter::hit('ai:global:daily', 86400);

        if ($tenantId) {
            RateLimiter::hit("ai:tenant:{$tenantId}:daily", 86400);
            RateLimiter::hit("ai:tenant:{$tenantId}:hourly", 3600);
        }
    }

    /**
     * Generate cache key for response caching.
     */
    protected function getCacheKey(string $prompt, ?string $systemPrompt, array $context): string
    {
        $prefix = config('ai.cache.prefix', 'ai_response_');
        $hash = md5(json_encode([
            'prompt' => $prompt,
            'system' => $systemPrompt,
            'context' => $context,
            'model' => $this->model,
        ]));

        return $prefix . $hash;
    }

    /**
     * Log AI usage for monitoring.
     */
    protected function logUsage(
        ?int $tenantId,
        string $feature,
        int $tokensUsed,
        bool $success,
        ?string $error = null
    ): void {
        if (!config('ai.logging.enabled')) {
            return;
        }

        try {
            AIUsageLog::create([
                'tenant_id' => $tenantId,
                'feature' => $feature,
                'model' => $this->model,
                'tokens_used' => $tokensUsed,
                'success' => $success,
                'error' => $error,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log AI usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Estimate token count (rough approximation).
     */
    protected function estimateTokens(string $text): int
    {
        // Rough estimate: ~4 characters per token for English
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * Create error response array.
     */
    protected function errorResponse(string $message, int $code = 500): array
    {
        return [
            'success' => false,
            'content' => null,
            'error' => $message,
            'cached' => false,
            'code' => $code,
        ];
    }

    /**
     * Get remaining rate limit for a tenant.
     */
    public function getRemainingQuota(?int $tenantId = null): array
    {
        $limits = config('ai.rate_limits');

        $globalRemaining = $limits['global_daily_limit'] - RateLimiter::attempts('ai:global:daily');

        $tenantDailyRemaining = null;
        $tenantHourlyRemaining = null;

        if ($tenantId) {
            $tenantDailyRemaining = $limits['tenant_daily_limit'] - RateLimiter::attempts("ai:tenant:{$tenantId}:daily");
            $tenantHourlyRemaining = $limits['tenant_hourly_limit'] - RateLimiter::attempts("ai:tenant:{$tenantId}:hourly");
        }

        return [
            'global_remaining' => max(0, $globalRemaining),
            'tenant_daily_remaining' => $tenantDailyRemaining !== null ? max(0, $tenantDailyRemaining) : null,
            'tenant_hourly_remaining' => $tenantHourlyRemaining !== null ? max(0, $tenantHourlyRemaining) : null,
        ];
    }
}
