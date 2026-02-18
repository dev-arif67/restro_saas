<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Super admin or restaurant admin can create users
        return $this->user() && (
            $this->user()->isSuperAdmin() || $this->user()->isRestaurantAdmin()
        );
    }

    public function rules(): array
    {
        $user = $this->user();

        // Super admin must provide tenant_id; restaurant admin's tenant is auto-assigned
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ];

        if ($user->isSuperAdmin()) {
            $rules['tenant_id'] = 'required|exists:tenants,id';
            $rules['role'] = 'required|in:restaurant_admin,staff,kitchen';
        } else {
            // Restaurant admin can only create staff or kitchen users
            $rules['role'] = 'required|in:staff,kitchen';
        }

        return $rules;
    }
}
