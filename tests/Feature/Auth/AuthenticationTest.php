<?php

use App\Models\User;

/**
 * Test that verifies the login screen can be rendered successfully.
 *
 * This test ensures that:
 * 1. The /login route is accessible and properly configured
 * 2. The login page renders without errors
 * 3. Returns a successful HTTP 200 status code
 *
 * @test
 */
test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

/**
 * Test that verifies users can successfully authenticate using the login screen.
 *
 * This test ensures that:
 * 1. A user can be created using the factory
 * 2. Valid credentials can be submitted to the /login endpoint
 * 3. The user is properly authenticated after login
 * 4. The user is redirected to the dashboard after successful authentication
 *
 * @test
 */
test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

/**
 * Test that verifies users cannot authenticate with invalid credentials.
 *
 * This test ensures that:
 * 1. A user can be created with valid credentials
 * 2. Authentication fails when providing an incorrect password
 * 3. The user remains unauthenticated (guest) after failed login attempt
 * 4. Security is maintained by rejecting invalid credentials
 *
 * @test
 */
test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

/**
 * Test that verifies users can successfully logout from the application.
 *
 * This test ensures that:
 * 1. A user can be created and authenticated
 * 2. The logout endpoint (/logout) is accessible to authenticated users
 * 3. After logout, the user is no longer authenticated (becomes guest)
 * 4. The user is redirected to the home page after logout
 *
 * @test
 */
test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
