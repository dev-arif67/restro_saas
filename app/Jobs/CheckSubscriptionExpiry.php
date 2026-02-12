<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionExpiry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('Running subscription expiry check...');

        // Find expired active subscriptions
        $expired = Subscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $subscription) {
            $subscription->markExpired();

            // Check if tenant has any other active subscription
            $hasActive = Subscription::where('tenant_id', $subscription->tenant_id)
                ->where('id', '!=', $subscription->id)
                ->active()
                ->exists();

            if (!$hasActive) {
                // Deactivate tenant
                Tenant::where('id', $subscription->tenant_id)
                    ->update(['is_active' => false]);

                Log::info("Tenant {$subscription->tenant_id} deactivated due to expired subscription.");
            }
        }

        Log::info("Processed {$expired->count()} expired subscriptions.");
    }
}
