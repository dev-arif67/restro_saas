<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends BaseApiController
{
    /**
     * Get the authenticated user's profile.
     */
    public function show(): JsonResponse
    {
        $user = auth()->user();
        $user->load('tenant');

        return $this->success($user);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $original = $user->toArray();
        $user->update($validated);

        AuditLogger::log('profile_updated', $user, $original, $user->fresh()->toArray());

        return $this->success($user->fresh(), 'Profile updated successfully.');
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password is incorrect.', 422);
        }

        $user->update([
            'password' => $request->password, // Hashed via 'hashed' cast
        ]);

        AuditLogger::log('password_changed', $user);

        return $this->success(null, 'Password changed successfully.');
    }
}
