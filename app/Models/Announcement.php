<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'type',
        'recipients_type',
        'specific_tenant_ids',
        'status',
        'sent_at',
        'created_by_id',
    ];

    protected $casts = [
        'specific_tenant_ids' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the user who created the announcement.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get users who have read this announcement.
     */
    public function readByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')
            ->withPivot('read_at')
            ->withTimestamps();
    }

    /**
     * Check if the announcement is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the announcement has been sent.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Mark the announcement as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Check if a user has read this announcement.
     */
    public function isReadByUser(User $user): bool
    {
        return $this->readByUsers()->where('user_id', $user->id)->exists();
    }

    /**
     * Mark as read by a user.
     */
    public function markAsReadBy(User $user): void
    {
        if (!$this->isReadByUser($user)) {
            $this->readByUsers()->attach($user->id, ['read_at' => now()]);
        }
    }

    /**
     * Get recipient tenants based on recipients_type.
     */
    public function getRecipientTenants()
    {
        return match ($this->recipients_type) {
            'all' => Tenant::all(),
            'active' => Tenant::where('is_active', true)->get(),
            'expiring' => Tenant::whereHas('activeSubscription', function ($q) {
                $q->where('expires_at', '<=', now()->addDays(30));
            })->get(),
            'specific' => Tenant::whereIn('id', $this->specific_tenant_ids ?? [])->get(),
            default => collect(),
        };
    }

    /**
     * Scope for sent announcements.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for draft announcements.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for active announcements (sent and includes in_app).
     */
    public function scopeActiveInApp($query)
    {
        return $query->where('status', 'sent')
            ->whereIn('type', ['in_app', 'both']);
    }
}
