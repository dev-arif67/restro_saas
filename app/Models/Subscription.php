<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'plan_type',
        'amount',
        'payment_method',
        'payment_ref',
        'transaction_id',
        'starts_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    /**
     * Get the subscription plan.
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>=', today());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '<', today());
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->gte(today());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lt(today());
    }

    public function daysRemaining(): int
    {
        return max(0, today()->diffInDays($this->expires_at, false));
    }

    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}
