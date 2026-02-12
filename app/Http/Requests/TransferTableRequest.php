<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_table_id' => 'required|exists:restaurant_tables,id',
            'to_table_id' => 'required|exists:restaurant_tables,id|different:from_table_id',
        ];
    }

    public function messages(): array
    {
        return [
            'to_table_id.different' => 'Cannot transfer to the same table.',
        ];
    }
}
