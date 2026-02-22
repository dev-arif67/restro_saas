<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AITenantSettings extends Model
{
    protected $table = 'ai_tenant_settings';

    protected $fillable = [
        'tenant_id',
        'ai_enabled',
        'enabled_features',
        'custom_prompts',
        'chatbot_name',
        'chatbot_greeting',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'enabled_features' => 'array',
        'custom_prompts' => 'array',
    ];

    /**
     * Get the tenant these settings belong to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if a specific feature is enabled for this tenant.
     */
    public function isFeatureEnabled(string $feature): bool
    {
        if (!$this->ai_enabled) {
            return false;
        }

        // Check tenant-specific override first
        if ($this->enabled_features !== null) {
            return in_array($feature, $this->enabled_features);
        }

        // Fall back to global config
        return config("ai.features.{$feature}", false);
    }

    /**
     * Get custom prompt for a feature, or fall back to default.
     */
    public function getPrompt(string $feature): string
    {
        $customPrompts = $this->custom_prompts ?? [];

        if (isset($customPrompts[$feature])) {
            return $customPrompts[$feature];
        }

        return config("ai.prompts.{$feature}", '');
    }

    /**
     * Get or create settings for a tenant.
     */
    public static function getForTenant(int $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'ai_enabled' => true,
                'chatbot_name' => 'AI Assistant',
                'chatbot_greeting' => 'Hello! How can I help you today?',
            ]
        );
    }

    /**
     * Get all enabled features for this tenant.
     */
    public function getEnabledFeatures(): array
    {
        if (!$this->ai_enabled) {
            return [];
        }

        if ($this->enabled_features !== null) {
            return $this->enabled_features;
        }

        // Return globally enabled features
        return array_keys(array_filter(config('ai.features', [])));
    }
}
