<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by the
    | application. You may set this to any of the providers defined below.
    |
    */

    'default' => env('AI_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the AI providers for your application. Currently
    | supported: "gemini", "openai", "claude"
    |
    */

    'providers' => [

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3-flash-preview'),
            'embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 30,
            'max_tokens' => 2048,
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'base_url' => 'https://api.openai.com/v1',
            'timeout' => 30,
            'max_tokens' => 2048,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits to stay within free tier limits and ensure fair
    | usage across tenants. Gemini free tier: 1,500 requests/day.
    |
    */

    'rate_limits' => [
        // Global daily limit (across all tenants)
        'global_daily_limit' => env('AI_GLOBAL_DAILY_LIMIT', 1400),

        // Per-tenant daily limit
        'tenant_daily_limit' => env('AI_TENANT_DAILY_LIMIT', 100),

        // Per-tenant hourly limit (burst protection)
        'tenant_hourly_limit' => env('AI_TENANT_HOURLY_LIMIT', 30),

        // Cooldown period in seconds when limit is reached
        'cooldown_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Cache AI responses to reduce API calls and improve response times
    | for frequently asked questions.
    |
    */

    'cache' => [
        'enabled' => env('AI_CACHE_ENABLED', true),
        'ttl' => env('AI_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'ai_response_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific AI features. These can be overridden
    | per-tenant in the database.
    |
    */

    'features' => [
        'analytics_assistant' => env('AI_FEATURE_ANALYTICS', true),
        'sales_forecast' => env('AI_FEATURE_FORECAST', true),
        'customer_chatbot' => env('AI_FEATURE_CHATBOT', true),
        'smart_recommendations' => env('AI_FEATURE_RECOMMENDATIONS', true),
        'menu_descriptions' => env('AI_FEATURE_DESCRIPTIONS', true),
        'sentiment_analysis' => env('AI_FEATURE_SENTIMENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | System Prompts
    |--------------------------------------------------------------------------
    |
    | Default system prompts for different AI features.
    |
    */

    'prompts' => [

        'analytics_assistant' => <<<'PROMPT'
You are an AI analytics assistant for a restaurant management platform. You help restaurant owners understand their business data.

You have access to the following data types:
- Orders: order details, totals, status, timestamps
- Menu Items: names, prices, categories, sales counts
- Revenue: daily/weekly/monthly totals
- Customers: order frequency, preferences

When asked questions:
1. Interpret the natural language query to understand what data is needed
2. Provide clear, concise insights
3. When relevant, suggest actionable recommendations
4. Format numbers and currency appropriately (use ৳ for BDT)
5. If you cannot answer with the available data, explain what's missing

Always be helpful, professional, and data-driven in your responses.
PROMPT,

        'customer_chatbot' => <<<'PROMPT'
You are a friendly AI assistant for {restaurant_name}. You help customers with:
- Menu questions (ingredients, allergens, recommendations)
- Order status inquiries
- Restaurant information (hours, location, policies)

Guidelines:
1. Be friendly, helpful, and concise
2. Only discuss this restaurant's menu and services
3. If asked about ordering, guide them to use the menu page
4. Never share pricing - direct them to check the menu
5. If you don't know something, say so politely
6. Keep responses brief (2-3 sentences max)

Restaurant Context:
{restaurant_context}
PROMPT,

        'menu_description' => <<<'PROMPT'
Generate an appetizing, professional menu description for a restaurant item.

Item Name: {item_name}
Category: {category}
Language: {language}

Guidelines:
1. Keep it concise (20-40 words)
2. Highlight key ingredients or cooking methods
3. Use sensory words (aromatic, crispy, tender, etc.)
4. Make it sound appetizing without being over-the-top
5. Match the tone to the restaurant style

Return ONLY the description, no additional text.
PROMPT,

        'sentiment_analysis' => <<<'PROMPT'
Analyze the sentiment of the following customer feedback or enquiry.

Text: {text}

Respond with a JSON object containing:
{
    "sentiment": "positive" | "neutral" | "negative",
    "confidence": 0.0-1.0,
    "summary": "brief one-line summary",
    "priority": "low" | "medium" | "high",
    "topics": ["array", "of", "topics"]
}

Only respond with the JSON, no additional text.
PROMPT,

        'sales_forecast' => <<<'PROMPT'
You are a sales forecasting assistant for a restaurant. Analyze the provided historical data and predict future trends.

Historical Data:
{historical_data}

Provide predictions for:
1. Expected revenue for the next 7 days
2. Predicted busy hours
3. Recommended inventory adjustments
4. Staffing suggestions

Format your response as actionable insights that a restaurant owner can use.
PROMPT,

    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure AI usage logging for monitoring and debugging.
    |
    */

    'logging' => [
        'enabled' => env('AI_LOGGING_ENABLED', true),
        'log_prompts' => env('AI_LOG_PROMPTS', false), // Privacy consideration
        'log_responses' => env('AI_LOG_RESPONSES', false),
        'retention_days' => env('AI_LOG_RETENTION_DAYS', 30),
    ],

];
