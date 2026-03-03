<?php

/**
 * Password Reset Tests (API)
 *
 * Tests the JWT-compatible password reset flow via PasswordResetController.
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('forgot password endpoint accepts email', function () {
    User::factory()->create(['email' => 'test@test.com']);

    $response = $this->postJson('/api/auth/forgot-password', [
        'email' => 'test@test.com',
    ]);

    // Always returns 200 to prevent email enumeration
    $response->assertOk()
        ->assertJsonPath('success', true);
});

test('forgot password does not reveal if email exists', function () {
    $response = $this->postJson('/api/auth/forgot-password', [
        'email' => 'nonexistent@test.com',
    ]);

    // Same response whether email exists or not
    $response->assertOk()
        ->assertJsonPath('success', true);
});

test('forgot password validates email field', function () {
    $response = $this->postJson('/api/auth/forgot-password', []);

    $response->assertStatus(422);
});
