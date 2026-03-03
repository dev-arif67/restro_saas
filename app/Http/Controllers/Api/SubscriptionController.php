<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::withoutGlobalScopes()->with('tenant:id,name');

        if ($tenantId = $request->get('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return $this->paginated($query->latest());
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        // Expire any existing active subscription
        Subscription::withoutGlobalScopes()
            ->where('tenant_id', $request->tenant_id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Auto-calculate dates from plan_type if not provided
        $startsAt = $request->starts_at ?? now();
        $expiresAt = $request->expires_at;

        if (!$expiresAt) {
            $planDays = match ($request->plan_type) {
                'monthly' => config('saas.plans.monthly.duration_days', 30),
                'yearly' => config('saas.plans.yearly.duration_days', 365),
                'custom' => $request->custom_days ?? 30,
                default => 30,
            };
            $expiresAt = now()->addDays($planDays);
        }

        $data = collect($request->validated())
            ->except(['starts_at', 'expires_at', 'custom_days'])
            ->toArray();

        $subscription = Subscription::withoutGlobalScopes()->create([
            ...$data,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => 'active',
        ]);

        // Ensure tenant is active
        Tenant::where('id', $request->tenant_id)
            ->update(['is_active' => true]);

        return $this->created(
            $subscription->load('tenant:id,name'),
            'Subscription created successfully'
        );
    }

    public function show(int $id): JsonResponse
    {
        $subscription = Subscription::withoutGlobalScopes()->with('tenant')->find($id);

        if (!$subscription) {
            return $this->notFound('Subscription not found');
        }

        return $this->success($subscription);
    }

    public function cancel(int $id): JsonResponse
    {
        $subscription = Subscription::withoutGlobalScopes()->find($id);

        if (!$subscription) {
            return $this->notFound('Subscription not found');
        }

        $subscription->update(['status' => 'cancelled']);

        return $this->success($subscription, 'Subscription cancelled');
    }

    public function currentSubscription(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        if (!$tenant) {
            return $this->error('No tenant found', 404);
        }

        $subscription = $tenant->activeSubscription;

        if (!$subscription) {
            return $this->success([
                'subscription' => null,
                'expired' => true,
                'message' => 'No active subscription',
            ]);
        }

        return $this->success([
            'subscription' => $subscription,
            'expired' => false,
            'days_remaining' => $subscription->daysRemaining(),
        ]);
    }

    /**
     * Payment initiation for subscription renewal (bKash / SSLCommerz).
     * Requires tenant with existing subscription (renewal flow).
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'nullable|in:sslcommerz,manual',
        ]);

        $user = auth()->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return $this->error('No restaurant found. Please complete onboarding first.', 422);
        }

        $plan = \App\Models\SubscriptionPlan::findOrFail($request->plan_id);
        $paymentMethod = $request->payment_method ?? 'sslcommerz';

        $tranId = 'RENEW-' . $tenant->id . '-' . time() . '-' . \Illuminate\Support\Str::random(6);

        // Store pending renewal data
        $renewalData = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'plan_type' => $plan->slug,
            'amount' => $plan->price,
            'duration_days' => $plan->duration_days,
            'tran_id' => $tranId,
        ];

        cache()->put("subscription_payment:{$tranId}", $renewalData, now()->addMinutes(30));

        $sslCommerz = new \App\Services\SslCommerzService();

        if (!$sslCommerz->isEnabled() || $paymentMethod === 'manual') {
            // Direct activation for dev/testing or manual payment
            Subscription::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $subscription = Subscription::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_type' => $plan->slug,
                'amount' => $plan->price,
                'payment_method' => 'manual',
                'transaction_id' => $tranId,
                'starts_at' => now(),
                'expires_at' => now()->addDays($plan->duration_days),
                'status' => 'active',
                'notes' => 'Self-service renewal (payment gateway not configured)',
            ]);

            Tenant::where('id', $tenant->id)->update(['is_active' => true]);

            cache()->forget("subscription_payment:{$tranId}");

            return $this->created([
                'subscription' => $subscription->load('tenant:id,name'),
                'plan' => $plan,
            ], 'Subscription renewed successfully.');
        }

        $baseUrl = config('app.url');

        $paymentResult = $sslCommerz->initiatePayment([
            'amount' => $plan->price,
            'currency' => $tenant->currency ?? 'BDT',
            'tran_id' => $tranId,
            'success_url' => "{$baseUrl}/api/onboarding/payment/success",
            'fail_url' => "{$baseUrl}/api/onboarding/payment/fail",
            'cancel_url' => "{$baseUrl}/api/onboarding/payment/cancel",
            'ipn_url' => "{$baseUrl}/api/onboarding/payment/ipn",
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $tenant->phone ?? '01700000000',
            'product_name' => "Subscription Renewal: {$plan->name}",
            'num_items' => 1,
        ]);

        if (!$paymentResult['success']) {
            return $this->error('Payment initiation failed. Please try again.', 500);
        }

        return $this->success([
            'payment_url' => $paymentResult['gateway_url'],
            'tran_id' => $tranId,
            'plan' => $plan,
            'amount' => $plan->price,
        ], 'Redirect to payment gateway to complete renewal.');
    }

    /**
     * Payment callback handler (legacy endpoint, kept for backward compatibility).
     */
    public function paymentCallback(Request $request): JsonResponse
    {
        $request->validate([
            'transaction_id' => 'required|string',
            'payment_ref' => 'required|string',
            'status' => 'required|in:success,failed',
        ]);

        if ($request->status !== 'success') {
            return $this->error('Payment failed');
        }

        // Check if subscription was already created via IPN/redirect callback
        $existing = Subscription::withoutGlobalScopes()
            ->where('transaction_id', $request->transaction_id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return $this->success([
                'subscription' => $existing->load('tenant:id,name'),
            ], 'Subscription already active.');
        }

        return $this->error('Payment could not be verified. Please contact support.', 422);
    }

    /**
     * Get subscriptions expiring soon (within 30 days).
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $now = Carbon::now();

        $critical = Subscription::withoutGlobalScopes()
            ->with('tenant:id,name,slug,email')
            ->active()
            ->where('expires_at', '<=', $now->copy()->addDays(7))
            ->orderBy('expires_at')
            ->get();

        $warning = Subscription::withoutGlobalScopes()
            ->with('tenant:id,name,slug,email')
            ->active()
            ->where('expires_at', '>', $now->copy()->addDays(7))
            ->where('expires_at', '<=', $now->copy()->addDays(14))
            ->orderBy('expires_at')
            ->get();

        $upcoming = Subscription::withoutGlobalScopes()
            ->with('tenant:id,name,slug,email')
            ->active()
            ->where('expires_at', '>', $now->copy()->addDays(14))
            ->where('expires_at', '<=', $now->copy()->addDays(30))
            ->orderBy('expires_at')
            ->get();

        return $this->success([
            'critical' => $critical, // Expires in ≤7 days
            'warning' => $warning,   // Expires in 8-14 days
            'upcoming' => $upcoming, // Expires in 15-30 days
            'counts' => [
                'critical' => $critical->count(),
                'warning' => $warning->count(),
                'upcoming' => $upcoming->count(),
                'total' => $critical->count() + $warning->count() + $upcoming->count(),
            ],
        ]);
    }

    /**
     * Extend a subscription by a number of days.
     */
    public function extend(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::withoutGlobalScopes()->find($id);

        if (!$subscription) {
            return $this->notFound('Subscription not found');
        }

        $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'reason' => 'nullable|string|max:500',
        ]);

        $original = $subscription->toArray();
        $oldExpiry = $subscription->expires_at->copy();

        // If subscription is expired, extend from today, otherwise extend from current expiry
        $baseDate = $subscription->isExpired() ? now() : $subscription->expires_at;
        $newExpiry = $baseDate->copy()->addDays($request->days);

        $subscription->update([
            'expires_at' => $newExpiry,
            'status' => 'active', // Reactivate if expired
            'notes' => $subscription->notes
                ? $subscription->notes . "\n[Extended on " . now()->format('Y-m-d') . ": +{$request->days} days. Reason: " . ($request->reason ?? 'N/A') . "]"
                : "[Extended on " . now()->format('Y-m-d') . ": +{$request->days} days. Reason: " . ($request->reason ?? 'N/A') . "]",
        ]);

        // Ensure tenant is active
        Tenant::where('id', $subscription->tenant_id)->update(['is_active' => true]);

        AuditLogger::logUpdated($subscription, $original);

        return $this->success([
            'subscription' => $subscription->fresh()->load('tenant:id,name'),
            'old_expiry' => $oldExpiry->format('Y-m-d'),
            'new_expiry' => $newExpiry->format('Y-m-d'),
            'days_added' => $request->days,
        ], "Subscription extended by {$request->days} days");
    }

    /**
     * Manually renew/create a new subscription for a tenant using a plan.
     */
    public function renewManual(Request $request, int $tenantId): JsonResponse
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return $this->notFound('Tenant not found');
        }

        $request->validate([
            'plan_id' => 'required_without:plan_type|exists:subscription_plans,id',
            'plan_type' => 'required_without:plan_id|in:monthly,yearly,custom',
            'custom_days' => 'required_if:plan_type,custom|integer|min:1',
            'custom_amount' => 'required_if:plan_type,custom|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'payment_ref' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        // Expire existing active subscriptions
        Subscription::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // If using a plan, get the plan details
        if ($request->plan_id) {
            $plan = SubscriptionPlan::findOrFail($request->plan_id);
            $amount = $plan->price;
            $duration = $plan->duration_days;
            $planType = $plan->slug;
        } else {
            $plan = null;
            $planType = $request->plan_type;

            $amount = match ($planType) {
                'monthly' => config('saas.plans.monthly.price', 999),
                'yearly' => config('saas.plans.yearly.price', 9999),
                'custom' => $request->custom_amount,
            };

            $duration = match ($planType) {
                'monthly' => config('saas.plans.monthly.duration_days', 30),
                'yearly' => config('saas.plans.yearly.duration_days', 365),
                'custom' => $request->custom_days,
            };
        }

        $subscription = Subscription::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'plan_id' => $plan?->id,
            'plan_type' => $planType,
            'amount' => $amount,
            'payment_method' => $request->payment_method ?? 'manual',
            'payment_ref' => $request->payment_ref,
            'starts_at' => now(),
            'expires_at' => now()->addDays($duration),
            'status' => 'active',
            'notes' => $request->notes ?? 'Manual renewal by super admin',
        ]);

        // Ensure tenant is active
        $tenant->update(['is_active' => true]);

        AuditLogger::logCreated($subscription);

        return $this->created([
            'subscription' => $subscription->load('tenant:id,name'),
            'plan' => $plan,
        ], 'Subscription renewed successfully');
    }
}
