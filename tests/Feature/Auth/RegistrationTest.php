<?php

/**
 * Registration Tests (API)
 *
 * Tests API user registration endpoint.
 */

test('new users can register via api', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['access_token', 'user']);
});

test('registration requires valid email', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test',
        'email' => 'not-an-email',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422);
});

test('registration requires password confirmation', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
});
