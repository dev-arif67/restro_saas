<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'discount_value' => 'required|numeric|min:0',
            'type' => 'required|in:fixed,percentage',
            'min_purchase' => 'nullable|numeric|min:0',
            'expiry_date' => 'required|date|after:today',
            'is_active' => 'boolean',
            'max_uses' => 'nullable|integer|min:1',
        ];
    }
}
