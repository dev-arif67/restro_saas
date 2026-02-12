<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => 'required|exists:tenants,id',
            'plan_type' => 'required|in:monthly,yearly,custom',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|in:bkash,sslcommerz,manual',
            'payment_ref' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'starts_at' => 'required|date',
            'expires_at' => 'required|date|after:starts_at',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
