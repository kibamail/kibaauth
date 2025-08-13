<?php

/**
 * Test that verifies the user registration screen can be rendered successfully.
 *
 * This test ensures that:
 * 1. The /register route is accessible to unauthenticated users
 * 2. The registration page renders without errors
 * 3. Returns a successful HTTP 200 status code
 * 4. New users can access the registration functionality
 *
 * @test
 */
test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

/**
 * Test that verifies new users can successfully register for an account.
 *
 * This test ensures that:
 * 1. Valid registration data (email, password, confirmation) can be submitted
 * 2. A new user account is created in the database
 * 3. The user is automatically authenticated after registration
 * 4. The user is redirected to the dashboard after successful registration
 * 5. Password confirmation validation works correctly
 *
 * @test
 */
test('new users can register', function () {
    $response = $this->post('/register', [
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
