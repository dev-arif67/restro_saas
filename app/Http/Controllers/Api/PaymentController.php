<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\SslCommerzService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends BaseApiController
{
    protected SslCommerzService $sslCommerz;

    public function __construct(SslCommerzService $sslCommerz)
    {
        $this->sslCommerz = $sslCommerz;
    }

    /**
     * Initiate SSLCommerz payment for an order.
     * Called after order is placed with payment_method = 'online'.
     */
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'order_number' => 'required|string',
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

        if (!$this->sslCommerz->isEnabled()) {
            return $this->error('Online payment is not available at this time', 503);
        }

        $tranId = 'ORDER-' . $order->order_number . '-' . time();
        $baseUrl = env('APP_FRONTEND_URL', 'http://192.168.0.165:5173');

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
        $status = $request->input('status');

        Log::info('SSLCommerz success callback', $request->all());

        if ($status === 'VALID' || $status === 'VALIDATED') {
            $order = $this->findOrderByTranId($tranId);

            if ($order && $order->payment_status !== 'paid') {
                // Validate with SSLCommerz
                $isValid = $this->sslCommerz->validatePayment($request->all());

                if ($isValid) {
                    $order->update([
                        'payment_status' => 'paid',
                        'paid_at'        => now(),
                        'transaction_id' => $valId ?: $tranId,
                    ]);
                }
            }

            // Redirect to order tracking page
            $orderNumber = $order?->order_number ?? '';
            return redirect("/order/{$orderNumber}?payment=success");
        }

        return redirect("/order/" . ($this->findOrderByTranId($tranId)?->order_number ?? '') . "?payment=failed");
    }

    /**
     * SSLCommerz fail callback.
     */
    public function handleFail(Request $request)
    {
        $tranId = $request->input('tran_id');
        Log::warning('SSLCommerz payment failed', $request->all());

        $order = $this->findOrderByTranId($tranId);
        $orderNumber = $order?->order_number ?? '';

        return redirect("/order/{$orderNumber}?payment=failed");
    }

    /**
     * SSLCommerz cancel callback.
     */
    public function handleCancel(Request $request)
    {
        $tranId = $request->input('tran_id');
        Log::info('SSLCommerz payment cancelled', $request->all());

        $order = $this->findOrderByTranId($tranId);
        $orderNumber = $order?->order_number ?? '';

        return redirect("/order/{$orderNumber}?payment=cancelled");
    }

    /**
     * SSLCommerz IPN (Instant Payment Notification) - server-to-server.
     */
    public function ipn(Request $request): JsonResponse
    {
        Log::info('SSLCommerz IPN received', $request->all());

        $tranId = $request->input('tran_id');
        $status = $request->input('status');

        if ($status === 'VALID' || $status === 'VALIDATED') {
            $order = $this->findOrderByTranId($tranId);

            if ($order && $order->payment_status !== 'paid') {
                $isValid = $this->sslCommerz->validatePayment($request->all());

                if ($isValid) {
                    $order->update([
                        'payment_status' => 'paid',
                        'paid_at'        => now(),
                        'transaction_id' => $request->input('val_id') ?: $tranId,
                    ]);

                    return $this->success(null, 'Payment confirmed via IPN');
                }
            }
        }

        return $this->error('IPN processing failed', 400);
    }

    /**
     * Find order by transaction ID pattern.
     */
    private function findOrderByTranId(?string $tranId): ?Order
    {
        if (!$tranId) return null;

        return Order::withoutGlobalScopes()
            ->where('transaction_id', $tranId)
            ->first();
    }
}
