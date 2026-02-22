<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIUsageLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_usage_logs';

    protected $fillable = [
        'tenant_id',
        'feature',
        'model',
        'tokens_used',
        'success',
        'error',
    ];

    protected $casts = [
        'success' => 'boolean',
        'tokens_used' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the tenant associated with this log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by feature.
     */
    public function scopeFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
    }

    /**
     * Scope to filter by success status.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to filter by failed status.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Get total tokens used for a tenant in a period.
     */
    public static function getTotalTokens(?int $tenantId, string $period = 'today'): int
    {
        $query = self::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
        }

        return (int) $query->sum('tokens_used');
    }

    /**
     * Get usage statistics grouped by feature.
     */
    public static function getUsageByFeature(?int $tenantId = null, string $period = 'month'): array
    {
        $query = self::query()
            ->selectRaw('feature, COUNT(*) as total_requests, SUM(tokens_used) as total_tokens, SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful')
            ->groupBy('feature');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
        }

        return $query->get()->toArray();
    }
}
