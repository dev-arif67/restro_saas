<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SslCommerzService
{
    protected string $storeId;
    protected string $storePassword;
    protected bool $sandbox;
    protected string $apiUrl;

    public function __construct()
    {
        $config = config('saas.payment_gateways.sslcommerz');

        $this->storeId = $config['store_id'] ?? '';
        $this->storePassword = $config['store_password'] ?? '';
        $this->sandbox = $config['sandbox'] ?? true;

        $this->apiUrl = $this->sandbox
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }

    /**
     * Initiate a payment session with SSLCommerz.
     *
     * @param array $data Order/payment data
     * @return array ['success' => bool, 'gateway_url' => string|null, 'session_key' => string|null]
     */
    public function initiatePayment(array $data): array
    {
        $postData = [
            'store_id'       => $this->storeId,
            'store_passwd'   => $this->storePassword,
            'total_amount'   => $data['amount'],
            'currency'       => $data['currency'] ?? 'BDT',
            'tran_id'        => $data['tran_id'],
            'success_url'    => $data['success_url'],
            'fail_url'       => $data['fail_url'],
            'cancel_url'     => $data['cancel_url'],
            'ipn_url'        => $data['ipn_url'] ?? $data['success_url'],

            // Customer info
            'cus_name'       => $data['customer_name'] ?? 'Customer',
            'cus_email'      => $data['customer_email'] ?? 'customer@example.com',
            'cus_phone'      => $data['customer_phone'] ?? '01700000000',
            'cus_add1'       => $data['customer_address'] ?? 'N/A',
            'cus_city'       => $data['customer_city'] ?? 'Dhaka',
            'cus_country'    => 'Bangladesh',

            // Shipping (required by SSLCommerz even for digital)
            'shipping_method' => 'NO',
            'num_of_item'    => $data['num_items'] ?? 1,
            'product_name'   => $data['product_name'] ?? 'Food Order',
            'product_category' => 'Food',
            'product_profile' => 'non-physical-goods',
        ];

        try {
            $response = Http::asForm()->post("{$this->apiUrl}/gwprocess/v4/api.php", $postData);

            $result = $response->json();

            if (isset($result['status']) && $result['status'] === 'SUCCESS') {
                return [
                    'success'     => true,
                    'gateway_url' => $result['GatewayPageURL'],
                    'session_key' => $result['sessionkey'] ?? null,
                ];
            }

            Log::warning('SSLCommerz initiation failed', ['response' => $result]);

            return [
                'success' => false,
                'gateway_url' => null,
                'error' => $result['failedreason'] ?? 'Payment initiation failed',
            ];
        } catch (\Exception $e) {
            Log::error('SSLCommerz exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'gateway_url' => null,
                'error' => 'Payment service unavailable',
            ];
        }
    }

    /**
     * Validate an IPN/callback from SSLCommerz.
     *
     * @param array $data The POST data from SSLCommerz callback
     * @return bool
     */
    public function validatePayment(array $data): bool
    {
        // In sandbox mode, simple validation
        if ($this->sandbox) {
            return isset($data['status']) && $data['status'] === 'VALID';
        }

        // Production: validate via API
        try {
            $validationId = $data['val_id'] ?? '';
            $response = Http::get("{$this->apiUrl}/validator/api/validationserverAPI.php", [
                'val_id'       => $validationId,
                'store_id'     => $this->storeId,
                'store_passwd' => $this->storePassword,
                'format'       => 'json',
            ]);

            $result = $response->json();

            return isset($result['status']) && $result['status'] === 'VALID';
        } catch (\Exception $e) {
            Log::error('SSLCommerz validation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check if SSLCommerz is enabled and configured.
     */
    public function isEnabled(): bool
    {
        $config = config('saas.payment_gateways.sslcommerz');
        return ($config['enabled'] ?? false) && !empty($this->storeId) && !empty($this->storePassword);
    }
}
