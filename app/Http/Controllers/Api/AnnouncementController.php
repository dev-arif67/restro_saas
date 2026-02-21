<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\SendAnnouncementEmail;
use App\Models\Announcement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AnnouncementController extends BaseApiController
{
    /**
     * List all announcements.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with('createdBy:id,name')
            ->withCount('readByUsers');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        return $this->paginated($query->latest());
    }

    /**
     * Store a new announcement.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'type' => 'required|in:in_app,email,both',
            'recipients_type' => 'required|in:all,active,expiring,specific',
            'specific_tenant_ids' => 'required_if:recipients_type,specific|array',
            'specific_tenant_ids.*' => 'integer|exists:tenants,id',
        ]);

        $announcement = Announcement::create([
            ...$validated,
            'status' => 'draft',
            'created_by_id' => auth()->id(),
        ]);

        AuditLogger::logCreated($announcement);

        return $this->created(
            $announcement->load('createdBy:id,name'),
            'Announcement created successfully'
        );
    }

    /**
     * Show a specific announcement.
     */
    public function show(int $id): JsonResponse
    {
        $announcement = Announcement::with('createdBy:id,name')
            ->withCount('readByUsers')
            ->find($id);

        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        // Get recipient count
        $recipientCount = $announcement->getRecipientTenants()->count();

        return $this->success([
            'announcement' => $announcement,
            'recipient_count' => $recipientCount,
        ]);
    }

    /**
     * Update an announcement (only drafts can be updated).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        if ($announcement->isSent()) {
            return $this->error('Cannot update a sent announcement', 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'type' => 'sometimes|in:in_app,email,both',
            'recipients_type' => 'sometimes|in:all,active,expiring,specific',
            'specific_tenant_ids' => 'required_if:recipients_type,specific|array',
            'specific_tenant_ids.*' => 'integer|exists:tenants,id',
        ]);

        $original = $announcement->toArray();
        $announcement->update($validated);

        AuditLogger::logUpdated($announcement, $original);

        return $this->success(
            $announcement->load('createdBy:id,name'),
            'Announcement updated successfully'
        );
    }

    /**
     * Delete an announcement.
     */
    public function destroy(int $id): JsonResponse
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        AuditLogger::logDeleted($announcement);
        $announcement->delete();

        return $this->success(null, 'Announcement deleted successfully');
    }

    /**
     * Send an announcement to recipients.
     */
    public function send(int $id): JsonResponse
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        if ($announcement->isSent()) {
            return $this->error('Announcement has already been sent', 422);
        }

        $tenants = $announcement->getRecipientTenants();
        $emailsSent = 0;

        // If email or both, send emails
        if (in_array($announcement->type, ['email', 'both'])) {
            foreach ($tenants as $tenant) {
                // Get tenant admin emails
                $adminEmails = User::where('tenant_id', $tenant->id)
                    ->where('role', User::ROLE_RESTAURANT_ADMIN)
                    ->pluck('email')
                    ->toArray();

                if ($tenant->email) {
                    $adminEmails[] = $tenant->email;
                }

                $adminEmails = array_unique($adminEmails);

                foreach ($adminEmails as $email) {
                    try {
                        Mail::raw($announcement->body, function ($mail) use ($email, $announcement) {
                            $mail->to($email)
                                ->subject($announcement->title)
                                ->from(config('mail.from.address'), config('mail.from.name'));
                        });
                        $emailsSent++;
                    } catch (\Exception $e) {
                        // Log error but continue
                        \Log::error("Failed to send announcement email to {$email}: " . $e->getMessage());
                    }
                }
            }
        }

        $announcement->markAsSent();

        AuditLogger::logAction('announcement_sent', $announcement, [
            'recipients_count' => $tenants->count(),
            'emails_sent' => $emailsSent,
        ]);

        return $this->success([
            'announcement' => $announcement->fresh(),
            'recipients_count' => $tenants->count(),
            'emails_sent' => $emailsSent,
        ], 'Announcement sent successfully');
    }

    /**
     * Get active in-app announcements for the current user.
     * Used by tenant admin dashboard to show announcements.
     */
    public function active(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return $this->success([]);
        }

        $tenantId = $user->tenant_id;

        // Get sent in-app announcements that apply to this tenant
        $announcements = Announcement::activeInApp()
            ->where(function ($query) use ($tenantId) {
                $query->where('recipients_type', 'all')
                    ->orWhere('recipients_type', 'active')
                    ->orWhere(function ($q) use ($tenantId) {
                        $q->where('recipients_type', 'specific')
                            ->whereJsonContains('specific_tenant_ids', $tenantId);
                    });
            })
            ->whereDoesntHave('readByUsers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderByDesc('sent_at')
            ->limit(5)
            ->get(['id', 'title', 'body', 'sent_at']);

        return $this->success($announcements);
    }

    /**
     * Mark an announcement as read.
     */
    public function markAsRead(int $id): JsonResponse
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            return $this->notFound('Announcement not found');
        }

        $announcement->markAsReadBy(auth()->user());

        return $this->success(null, 'Announcement marked as read');
    }
}
