<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\SslCommerzService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends BaseApiController
{
    protected SslCommerzService $sslCommerz;

    public function __construct(
        SslCommerzService $sslCommerz,
        protected SubscriptionService $subscriptionService
    ) {
        $this->sslCommerz = $sslCommerz;
    }

    public function subscriptionCallback(Request $request): JsonResponse
    {
        return $this->handleSubscriptionGatewayPayload($request, 'sslcommerz');
    }

    public function bkashSubscriptionCallback(Request $request): JsonResponse
    {
        return $this->handleSubscriptionGatewayPayload($request, 'bkash');
    }

    /**
     * Initiate SSLCommerz payment for an order.
     * Called after order is placed with payment_method = 'online'.
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => 'required|string|max:100',
        ]);

        $order = Order::withoutGlobalScopes()
            ->where('order_number', $request->order_number)
            ->first();

        if (!$order) {
            return $this->notFound('Order not found');
        }

        if ($order->payment_status === 'paid') {
            return $this->error('Order is already paid', 422);
        }

        if ($order->payment_method !== 'online') {
            return $this->error('This order is not configured for online payment', 422);
        }

        if (in_array($order->status, ['cancelled'], true)) {
            return $this->error('Cancelled orders cannot be paid online', 422);
        }

        if (!$this->sslCommerz->isEnabled()) {
            return $this->error('Online payment is not available at this time', 503);
        }

        $tranId = 'ORDER-' . $order->order_number . '-' . time();
        $baseUrl = rtrim(config('app.url'), '/');

        $result = $this->sslCommerz->initiatePayment([
            'amount'         => $order->grand_total,
            'currency'       => 'BDT',
            'tran_id'        => $tranId,
            'success_url'    => "{$baseUrl}/api/payment/sslcommerz/success",
            'fail_url'       => "{$baseUrl}/api/payment/sslcommerz/fail",
            'cancel_url'     => "{$baseUrl}/api/payment/sslcommerz/cancel",
            'ipn_url'        => "{$baseUrl}/api/payment/sslcommerz/ipn",
            'customer_name'  => $order->customer_name ?? 'Customer',
            'customer_phone' => $order->customer_phone ?? '01700000000',
            'product_name'   => "Order #{$order->order_number}",
            'num_items'      => $order->items()->count(),
        ]);

        if ($result['success']) {
            // Store transaction ID on the order
            $order->update([
                'transaction_id'  => $tranId,
                'payment_gateway' => 'sslcommerz',
            ]);

            return $this->success([
                'payment_url' => $result['gateway_url'],
                'tran_id'     => $tranId,
            ], 'Payment initiated');
        }

        return $this->error($result['error'] ?? 'Could not initiate payment', 500);
    }

    /**
     * SSLCommerz success callback (POST redirect from gateway).
     */
    public function handleSuccess(Request $request)
    {
        $tranId = $request->input('tran_id');
        $valId = $request->input('val_id');

        Log::info('SSLCommerz success callback', $request->all());

        $order = $this->findOrderByTranId($tranId);

        if (!$order) {
            return $this->redirectToOrder($order, 'failed');
        }

        $isValid = $this->sslCommerz->validatePayment($request->all());

        if (!$isValid) {
            Log::warning('SSLCommerz success callback validation failed', ['tran_id' => $tranId]);
            return $this->redirectToOrder($order, 'failed');
        }

        if ($order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'paid_at'        => now(),
                'transaction_id' => $valId ?: $tranId,
            ]);
        }

        return $this->redirectToOrder($order, 'success');
    }

    /**
     * SSLCommerz fail callback.
     */
    public function handleFail(Request $request)
    {
        $tranId = $request->input('tran_id');
        Log::warning('SSLCommerz payment failed', $request->all());

        $order = $this->findOrderByTranId($tranId);
        return $this->redirectToOrder($order, 'failed');
    }

    /**
     * SSLCommerz cancel callback.
     */
    public function handleCancel(Request $request)
    {
        $tranId = $request->input('tran_id');
        Log::info('SSLCommerz payment cancelled', $request->all());

        $order = $this->findOrderByTranId($tranId);
        return $this->redirectToOrder($order, 'cancelled');
    }

    /**
     * SSLCommerz IPN (Instant Payment Notification) - server-to-server.
     */
    public function ipn(Request $request): JsonResponse
    {
        Log::info('SSLCommerz IPN received', $request->all());

        $tranId = $request->input('tran_id');

        if ($this->isSubscriptionTranId($tranId)) {
            return $this->handleSubscriptionGatewayPayload($request, 'sslcommerz');
        }

        $order = $this->findOrderByTranId($tranId);

        if (!$order) {
            return $this->error('Order not found for transaction', 404);
        }

        $isValid = $this->sslCommerz->validatePayment($request->all());

        if (!$isValid) {
            Log::warning('SSLCommerz IPN validation failed', ['tran_id' => $tranId]);
            return $this->error('Invalid payment notification', 400);
        }

        if ($order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'paid_at'        => now(),
                'transaction_id' => $request->input('val_id') ?: $tranId,
            ]);

            return $this->success(null, 'Payment confirmed via IPN');
        }

        return $this->success(null, 'Payment already confirmed');
    }

    private function handleSubscriptionGatewayPayload(Request $request, string $gateway): JsonResponse
    {
        $tranId = $request->input('tran_id') ?: $request->input('transaction_id');
        $isSuccess = in_array($request->input('status'), ['VALID', 'success', 'SUCCESS', 'Completed'], true)
            || $request->boolean('success')
            || !empty($request->input('val_id'));

        if (!$tranId) {
            return $this->error('Missing transaction reference', 422);
        }

        if (!$isSuccess) {
            return $this->error('Payment not successful', 422);
        }

        $pending = cache()->get("subscription_payment:{$tranId}");

        if (!$pending) {
            return $this->error('Invalid or expired payment metadata', 422);
        }

        $tenant = Tenant::find($pending['tenant_id'] ?? null);
        $plan = SubscriptionPlan::find($pending['plan_id'] ?? null);

        if (!$tenant || !$plan) {
            return $this->error('Invalid tenant or plan metadata', 422);
        }

        $existing = Subscription::withoutGlobalScopes()
            ->where('transaction_id', $tranId)
            ->whereIn('status', ['active', 'grace'])
            ->first();

        if ($existing) {
            return $this->success([
                'subscription_id' => $existing->id,
                'redirect_url' => rtrim(config('app.frontend_url', config('app.url')), '/') . '/dashboard/subscription?status=success',
            ], 'Subscription already active');
        }

        $subscription = $this->subscriptionService->createSubscription(
            tenant: $tenant,
            plan: $plan,
            paymentData: [
                'amount' => $pending['amount'] ?? $plan->price,
                'payment_method' => $gateway,
                'payment_ref' => $request->input('val_id') ?? $request->input('payment_ref'),
                'transaction_id' => $tranId,
                'notes' => 'Auto-created via payment callback',
            ],
            isTrial: false,
            initiatedBy: 'tenant'
        );

        cache()->forget("subscription_payment:{$tranId}");

        return $this->success([
            'subscription_id' => $subscription->id,
            'redirect_url' => rtrim(config('app.frontend_url', config('app.url')), '/') . '/dashboard/subscription?status=success',
        ], 'Subscription activated successfully');
    }

    private function isSubscriptionTranId(?string $tranId): bool
    {
        if (!$tranId) {
            return false;
        }

        return str_starts_with($tranId, 'SUB-') || str_starts_with($tranId, 'RENEW-');
    }

    /**
     * Find order by transaction ID pattern.
     * Handles race condition where IPN may update transaction_id before success callback.
     */
    private function findOrderByTranId(?string $tranId): ?Order
    {
        if (!$tranId) return null;

        // Try exact match first
        $order = Order::withoutGlobalScopes()
            ->where('transaction_id', $tranId)
            ->first();

        if ($order) return $order;

        // Fallback: extract order number from tran_id format ORDER-{order_number}-{timestamp}
        if (preg_match('/^ORDER-(.+)-\d+$/', $tranId, $matches)) {
            return Order::withoutGlobalScopes()
                ->where('order_number', $matches[1])
                ->first();
        }

        return null;
    }

    private function redirectToOrder(?Order $order, string $paymentStatus)
    {
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');

        if ($order?->order_number) {
            $query = [
                'payment' => $paymentStatus,
            ];

            if (!empty($order->public_access_token)) {
                $query['access_token'] = $order->public_access_token;
            }

            return redirect($frontendUrl . '/order/' . $order->order_number . '?' . http_build_query($query));
        }

        return redirect($frontendUrl . '/?payment=' . urlencode($paymentStatus));
    }
}
