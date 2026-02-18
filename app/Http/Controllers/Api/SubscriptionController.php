<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Payment initiation (bKash / SSLCommerz)
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'plan_type' => 'required|in:monthly,yearly,custom',
            'payment_method' => 'required|in:bkash,sslcommerz',
        ]);

        $amount = match ($request->plan_type) {
            'monthly' => config('saas.plans.monthly.price', 999),
            'yearly' => config('saas.plans.yearly.price', 9999),
            'custom' => $request->get('custom_amount', 999),
        };

        // Return payment URL/config for frontend to handle
        // In production, integrate with bKash/SSLCommerz SDK
        return $this->success([
            'plan_type' => $request->plan_type,
            'amount' => $amount,
            'payment_method' => $request->payment_method,
            'payment_url' => '#', // Replace with actual payment gateway URL
            'reference' => 'PAY-' . uniqid(),
        ]);
    }

    /**
     * Payment callback handler
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

        // Verify with payment gateway in production
        // Then create subscription

        return $this->success(null, 'Payment processed. Subscription will be activated.');
    }
}
