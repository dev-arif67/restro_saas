<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:tenants,email,{$tenantId}",
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'logo' => 'nullable|image|max:2048',
            'authorized_wifi_ip' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|in:platform,seller',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
