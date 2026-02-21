<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => 'nullable|exists:restaurant_tables,id',
            'type' => 'required|in:dine,parcel',
            'customer_name' => 'required_if:type,parcel|nullable|string|max:255',
            'customer_phone' => 'required_if:type,parcel|nullable|string|max:20',
            'voucher_code' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'payment_method' => 'required|in:cash,online,pay_later',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.qty' => 'required|integer|min:1|max:100',
            'items.*.special_instructions' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required_if' => 'Customer name is required for parcel orders.',
            'customer_phone.required_if' => 'Customer phone is required for parcel orders.',
            'items.required' => 'At least one item is required.',
            'items.*.menu_item_id.exists' => 'One or more selected items are no longer available.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Invalid payment method.',
        ];
    }
}
