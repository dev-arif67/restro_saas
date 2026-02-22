<?php

namespace App\Services\AI;

class MenuDescriptionService
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
     * Generate a description for a menu item.
     */
    public function generateDescription(
        string $itemName,
        ?string $categoryName = null,
        ?float $price = null,
        array $options = []
    ): array {
        if (!$this->gemini->isConfigured()) {
            return $this->generateFallbackDescription($itemName, $categoryName);
        }

        $style = $options['style'] ?? 'appetizing';
        $length = $options['length'] ?? 'medium';
        $language = $options['language'] ?? 'en';

        $prompt = $this->buildPrompt($itemName, $categoryName, $price, $style, $length, $language);
        $systemPrompt = config('ai.prompts.menu_description');

        $response = $this->gemini->chat(
            $prompt,
            $systemPrompt,
            [],
            $this->tenantId,
            'menu_description'
        );

        if (!$response['success']) {
            return $this->generateFallbackDescription($itemName, $categoryName);
        }

        // Clean up the response
        $description = $this->cleanDescription($response['content']);

        return [
            'success' => true,
            'description' => $description,
            'alternatives' => [],
        ];
    }

    /**
     * Generate multiple description alternatives.
     */
    public function generateAlternatives(
        string $itemName,
        ?string $categoryName = null,
        ?float $price = null,
        int $count = 3
    ): array {
        if (!$this->gemini->isConfigured()) {
            return [
                'success' => true,
                'descriptions' => [$this->generateFallbackDescription($itemName, $categoryName)['description']],
            ];
        }

        $prompt = <<<PROMPT
Generate exactly {$count} different appetizing descriptions for this menu item:

Item: {$itemName}
Category: {$categoryName}
Price: ৳{$price}

Requirements:
- Each description should be 15-25 words
- Make them appetizing and descriptive
- Highlight flavors, textures, or cooking methods
- Keep them unique from each other

Return ONLY the descriptions, numbered 1-{$count}, one per line.
PROMPT;

        $systemPrompt = config('ai.prompts.menu_description');

        $response = $this->gemini->chat(
            $prompt,
            $systemPrompt,
            [],
            $this->tenantId,
            'menu_description'
        );

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => 'Failed to generate alternatives',
            ];
        }

        // Parse numbered descriptions
        $descriptions = $this->parseNumberedDescriptions($response['content'], $count);

        return [
            'success' => true,
            'descriptions' => $descriptions,
        ];
    }

    /**
     * Generate descriptions for multiple items at once (batch).
     */
    public function generateBatch(array $items): array
    {
        if (!$this->gemini->isConfigured()) {
            return [
                'success' => true,
                'results' => collect($items)->map(fn($item) => [
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'],
                    'description' => $this->generateFallbackDescription($item['name'], $item['category'] ?? null)['description'],
                ])->toArray(),
            ];
        }

        // Build batch prompt
        $itemsList = collect($items)->map(function ($item, $index) {
            $num = $index + 1;
            $category = $item['category'] ?? 'General';
            return "{$num}. {$item['name']} (Category: {$category})";
        })->implode("\n");

        $count = count($items);

        $prompt = <<<PROMPT
Generate short, appetizing descriptions for these {$count} menu items:

{$itemsList}

Requirements:
- Each description should be 10-20 words
- Make them appetizing and descriptive
- Match the style to the category

Return descriptions in this exact format:
1. [description for item 1]
2. [description for item 2]
etc.
PROMPT;

        $response = $this->gemini->chat(
            $prompt,
            config('ai.prompts.menu_description'),
            [],
            $this->tenantId,
            'menu_description'
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => 'Failed to generate batch descriptions'];
        }

        $descriptions = $this->parseNumberedDescriptions($response['content'], $count);

        $results = collect($items)->map(function ($item, $index) use ($descriptions) {
            return [
                'id' => $item['id'] ?? null,
                'name' => $item['name'],
                'description' => $descriptions[$index] ?? $this->generateFallbackDescription($item['name'])['description'],
            ];
        })->toArray();

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * Improve an existing description.
     */
    public function improveDescription(
        string $itemName,
        string $currentDescription,
        ?string $categoryName = null
    ): array {
        if (!$this->gemini->isConfigured()) {
            return ['success' => false, 'error' => 'AI service not configured'];
        }

        $prompt = <<<PROMPT
Improve this menu item description to make it more appetizing:

Item: {$itemName}
Category: {$categoryName}
Current description: {$currentDescription}

Requirements:
- Keep it 15-25 words
- Make it more appetizing and engaging
- Highlight sensory details (taste, texture, aroma)
- Keep any accurate factual information

Return ONLY the improved description, nothing else.
PROMPT;

        $response = $this->gemini->chat(
            $prompt,
            config('ai.prompts.menu_description'),
            [],
            $this->tenantId,
            'menu_description'
        );

        if (!$response['success']) {
            return ['success' => false, 'error' => 'Failed to improve description'];
        }

        return [
            'success' => true,
            'description' => $this->cleanDescription($response['content']),
            'original' => $currentDescription,
        ];
    }

    /**
     * Build the generation prompt.
     */
    protected function buildPrompt(
        string $itemName,
        ?string $categoryName,
        ?float $price,
        string $style,
        string $length,
        string $language
    ): string {
        $wordCount = match ($length) {
            'short' => '8-12',
            'long' => '25-35',
            default => '15-20',
        };

        $styleInstructions = match ($style) {
            'formal' => 'Use formal, elegant language suitable for fine dining.',
            'casual' => 'Use casual, friendly language for a relaxed atmosphere.',
            'playful' => 'Use fun, playful language with creative descriptions.',
            default => 'Use appetizing, descriptive language that highlights flavors and textures.',
        };

        $languageInstruction = $language === 'bn'
            ? 'Write the description in Bengali (বাংলা).'
            : 'Write the description in English.';

        $categoryContext = $categoryName
            ? "This item belongs to the '{$categoryName}' category."
            : '';

        $priceContext = $price
            ? "Price point: ৳{$price}"
            : '';

        return <<<PROMPT
Generate an appetizing menu description for:

Item name: {$itemName}
{$categoryContext}
{$priceContext}

Requirements:
- Word count: {$wordCount} words
- {$styleInstructions}
- {$languageInstruction}
- Focus on taste, texture, ingredients, or cooking method
- Be specific but not overly technical
- Make it enticing for customers

Return ONLY the description, no quotes or extra text.
PROMPT;
    }

    /**
     * Generate fallback description without AI.
     */
    protected function generateFallbackDescription(string $itemName, ?string $categoryName = null): array
    {
        $templates = [
            'appetizer' => [
                "A delicious {$itemName} to start your meal.",
                "Enjoy our freshly prepared {$itemName}.",
            ],
            'main' => [
                "Our signature {$itemName}, prepared with care.",
                "A hearty serving of {$itemName} you'll love.",
            ],
            'dessert' => [
                "A sweet treat of {$itemName} to end your meal perfectly.",
                "Indulge in our delightful {$itemName}.",
            ],
            'beverage' => [
                "Refreshing {$itemName} to complement your meal.",
                "A perfectly prepared {$itemName}.",
            ],
            'default' => [
                "Our delicious {$itemName}, made with quality ingredients.",
                "Enjoy our freshly prepared {$itemName}.",
            ],
        ];

        $category = strtolower($categoryName ?? 'default');
        $categoryKey = 'default';

        foreach (['appetizer', 'starter', 'soup', 'salad'] as $key) {
            if (str_contains($category, $key)) {
                $categoryKey = 'appetizer';
                break;
            }
        }

        foreach (['main', 'entrée', 'entree', 'rice', 'curry', 'meat', 'fish', 'chicken'] as $key) {
            if (str_contains($category, $key)) {
                $categoryKey = 'main';
                break;
            }
        }

        foreach (['dessert', 'sweet', 'cake', 'ice cream'] as $key) {
            if (str_contains($category, $key)) {
                $categoryKey = 'dessert';
                break;
            }
        }

        foreach (['drink', 'beverage', 'juice', 'tea', 'coffee', 'soda'] as $key) {
            if (str_contains($category, $key)) {
                $categoryKey = 'beverage';
                break;
            }
        }

        $options = $templates[$categoryKey] ?? $templates['default'];
        $description = $options[array_rand($options)];

        return [
            'success' => true,
            'description' => $description,
            'fallback' => true,
        ];
    }

    /**
     * Clean up AI-generated description.
     */
    protected function cleanDescription(string $description): string
    {
        // Remove surrounding quotes
        $description = trim($description, "\"'");

        // Remove "Description:" prefix if present
        $description = preg_replace('/^(Description|Here\'s?|The description):?\s*/i', '', $description);

        // Remove markdown formatting
        $description = preg_replace('/\*\*(.*?)\*\*/', '$1', $description);
        $description = preg_replace('/\*(.*?)\*/', '$1', $description);

        // Remove line breaks and extra spaces
        $description = preg_replace('/\s+/', ' ', $description);

        return trim($description);
    }

    /**
     * Parse numbered descriptions from AI response.
     */
    protected function parseNumberedDescriptions(string $content, int $expectedCount): array
    {
        $descriptions = [];

        // Match patterns like "1. description" or "1) description" or "1: description"
        preg_match_all('/\d+[\.\)\:]\s*(.+?)(?=\d+[\.\)\:]|$)/s', $content, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $desc) {
                $cleaned = $this->cleanDescription($desc);
                if (!empty($cleaned)) {
                    $descriptions[] = $cleaned;
                }
            }
        }

        // If parsing failed, try splitting by newlines
        if (count($descriptions) < $expectedCount) {
            $lines = array_filter(array_map('trim', explode("\n", $content)));
            foreach ($lines as $line) {
                $cleaned = $this->cleanDescription(preg_replace('/^\d+[\.\)\:]\s*/', '', $line));
                if (!empty($cleaned) && strlen($cleaned) > 10) {
                    $descriptions[] = $cleaned;
                }
            }
        }

        return array_slice(array_unique($descriptions), 0, $expectedCount);
    }
}
