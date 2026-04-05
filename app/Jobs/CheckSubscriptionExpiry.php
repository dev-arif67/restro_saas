<?php

namespace App\Jobs;

use App\Mail\SubscriptionExpiredMail;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckSubscriptionExpiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('Running subscription expiry check...');

        // Find expired active subscriptions (use today() for date columns)
        $expired = Subscription::withoutGlobalScopes()
            ->with('tenant')
            ->where('status', 'active')
            ->where('expires_at', '<', today())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->markExpired();

            // Check if tenant has any other active subscription
            $hasActive = Subscription::withoutGlobalScopes()
                ->where('tenant_id', $subscription->tenant_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->where('expires_at', '>=', today())
                ->exists();

            if (!$hasActive) {
                // Deactivate tenant
                Tenant::where('id', $subscription->tenant_id)
                    ->update(['is_active' => false]);

                Log::info("Tenant {$subscription->tenant_id} deactivated due to expired subscription.");

                // Send expiry notification email
                $this->sendExpiryEmail($subscription);
            }
        }

        Log::info("Processed {$expired->count()} expired subscriptions.");

        // Deactivate tenants whose trial has expired with no active paid subscription
        $expiredTrials = Tenant::where('is_active', true)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('status', 'active')->where('expires_at', '>=', today()))
            ->get();

        foreach ($expiredTrials as $tenant) {
            $tenant->update(['is_active' => false]);
            Log::info("Tenant {$tenant->id} deactivated — free trial expired.");
        }

        Log::info("Processed {$expiredTrials->count()} expired trials.");
    }

    /**
     * Send subscription expired email to tenant admins.
     */
    protected function sendExpiryEmail(Subscription $subscription): void
    {
        $tenant = $subscription->tenant;

        if (!$tenant) {
            return;
        }

        $adminEmails = User::where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_RESTAURANT_ADMIN)
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();

        if (empty($adminEmails)) {
            $adminEmails = [$tenant->email];
        }

        foreach ($adminEmails as $email) {
            try {
                Mail::to($email)->send(new SubscriptionExpiredMail(
                    tenant: $tenant,
                    subscription: $subscription,
                ));
            } catch (\Exception $e) {
                Log::error("Failed to send expiry email to {$email} for tenant {$tenant->id}: " . $e->getMessage());
            }
        }
    }
}
