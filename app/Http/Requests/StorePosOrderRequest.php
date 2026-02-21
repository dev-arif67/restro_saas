<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'                         => ['required', 'in:dine,parcel,quick'],
            'table_id'                     => ['nullable', 'integer'],
            'customer_name'                => ['nullable', 'string', 'max:100'],
            'customer_phone'               => ['nullable', 'string', 'max:20'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.menu_item_id'         => ['required', 'integer'],
            'items.*.qty'                  => ['required', 'integer', 'min:1'],
            'items.*.special_instructions' => ['nullable', 'string', 'max:255'],
            'voucher_code'                 => ['nullable', 'string'],
            'notes'                        => ['nullable', 'string', 'max:500'],
            'payment_method'               => ['required', 'in:cash,card,mobile_banking'],
            'payment_status'               => ['required', 'in:paid,pending'],
            'transaction_id'               => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'    => 'Order type is required.',
            'type.in'          => 'Invalid order type. Must be dine, parcel, or quick.',
            'items.required'   => 'At least one item is required.',
            'items.min'        => 'At least one item is required.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in'       => 'Invalid payment method.',
        ];
    }
}
