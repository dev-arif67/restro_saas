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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::withoutGlobalScopes()->where('status', '=', 'active')->with('tenant:id,name');

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
        $tenant = Auth::user()?->tenant;

        if (!$tenant) {
            return $this->error('No tenant found', 404);
        }

        $subscription = $tenant->activeSubscription;

        return $this->success([
            'subscription' => $subscription,
            'expired' => !$tenant->hasAccessRights(),
            'is_on_trial' => $tenant->isOnTrial(),
            'trial_days_remaining' => $tenant->trialDaysRemaining(),
            'trial_ends_at' => $tenant->trial_ends_at,
            'days_remaining' => $subscription?->daysRemaining() ?? 0,
            'message' => !$subscription
                ? ($tenant->isOnTrial() ? 'Free trial active' : 'No active subscription')
                : null,
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

        $user = Auth::user();
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
            'plan_type' => $plan->subscriptionType(),
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
                'plan_type' => $plan->subscriptionType(),
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

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required_without:plan_type|exists:subscription_plans,id',
            'plan_type' => 'required_without:plan_id|in:monthly,yearly,custom',
            'custom_days' => 'nullable|integer|min:1',
            'custom_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'payment_ref' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $validator->sometimes('custom_days', 'required|integer|min:1', function ($input) {
            return empty($input->plan_id) && ($input->plan_type ?? null) === 'custom';
        });

        $validator->sometimes('custom_amount', 'required|numeric|min:0', function ($input) {
            return empty($input->plan_id) && ($input->plan_type ?? null) === 'custom';
        });

        $validated = $validator->validate();

        $currentSubscription = Subscription::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->latest('expires_at')
            ->first();

        if (!empty($validated['plan_id'])) {
            $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
            $amount = $plan->price;
            $duration = $plan->duration_days;
            $planType = $plan->subscriptionType();
        } else {
            $plan = null;
            $planType = $validated['plan_type'];

            $amount = match ($planType) {
                'monthly' => config('saas.plans.monthly.price', 999),
                'yearly' => config('saas.plans.yearly.price', 9999),
                'custom' => $validated['custom_amount'],
            };

            $duration = match ($planType) {
                'monthly' => config('saas.plans.monthly.duration_days', 30),
                'yearly' => config('saas.plans.yearly.duration_days', 365),
                'custom' => $validated['custom_days'],
            };
        }

        $renewalBaseDate = $currentSubscription?->expires_at?->copy() ?? now();

        if ($renewalBaseDate->isPast()) {
            $renewalBaseDate = now();
        }

        $startsAt = now();
        $expiresAt = $renewalBaseDate->copy()->addDays($duration);

        $subscription = DB::transaction(function () use (
            $tenant,
            $tenantId,
            $validated,
            $plan,
            $planType,
            $amount,
            $duration,
            $startsAt,
            $expiresAt,
            $currentSubscription,
            $renewalBaseDate
        ) {
            Subscription::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $subscription = Subscription::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan?->id,
                'plan_type' => $planType,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'] ?? 'manual',
                'payment_ref' => $validated['payment_ref'] ?? null,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'notes' => $validated['notes'] ?? 'Manual renewal by super admin',
            ]);

            $tenant->update(['is_active' => true]);

            AuditLogger::log('subscription_renewed', $subscription, $currentSubscription ? [
                'subscription_id' => $currentSubscription->id,
                'tenant_id' => $currentSubscription->tenant_id,
                'plan_id' => $currentSubscription->plan_id,
                'plan_type' => $currentSubscription->plan_type,
                'amount' => $currentSubscription->amount,
                'payment_method' => $currentSubscription->payment_method,
                'payment_ref' => $currentSubscription->payment_ref,
                'transaction_id' => $currentSubscription->transaction_id,
                'starts_at' => $currentSubscription->starts_at?->toDateString(),
                'expires_at' => $currentSubscription->expires_at?->toDateString(),
                'status' => $currentSubscription->status,
            ] : null, [
                'tenant_id' => $tenantId,
                'plan_id' => $plan?->id,
                'plan_type' => $planType,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'] ?? 'manual',
                'payment_ref' => $validated['payment_ref'] ?? null,
                'starts_at' => $startsAt->toDateString(),
                'expires_at' => $expiresAt->toDateString(),
                'renewal_base_date' => $renewalBaseDate->toDateString(),
                'extension_days' => $duration,
                'notes' => $validated['notes'] ?? 'Manual renewal by super admin',
            ]);

            return $subscription;
        });

        return $this->created([
            'subscription' => $subscription->load('tenant:id,name'),
            'plan' => $plan,
            'renewal_base_date' => $renewalBaseDate->toDateString(),
            'expires_at' => $expiresAt->toDateString(),
        ], 'Subscription renewed successfully');
    }
}
