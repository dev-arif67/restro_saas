<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIConversation extends Model
{
    protected $table = 'ai_conversations';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'session_id',
        'feature',
        'messages',
        'metadata',
    ];

    protected $casts = [
        'messages' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns this conversation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that owns this conversation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add a message to the conversation.
     */
    public function addMessage(string $role, string $content): self
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toISOString(),
        ];
        $this->messages = $messages;
        $this->save();

        return $this;
    }

    /**
     * Get the last N messages for context.
     */
    public function getContextMessages(int $limit = 10): array
    {
        $messages = $this->messages ?? [];
        return array_slice($messages, -$limit);
    }

    /**
     * Scope to filter by feature.
     */
    public function scopeFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
    }

    /**
     * Scope to filter by session.
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}
