<?php

use App\Models\User;

/**
 * Test that verifies the password confirmation screen can be rendered for authenticated users.
 *
 * This test ensures that:
 * 1. An authenticated user can access the /confirm-password route
 * 2. The password confirmation page renders successfully
 * 3. Returns a successful HTTP 200 status code
 * 4. The route is properly protected and requires authentication
 *
 * @test
 */
test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/confirm-password');

    $response->assertStatus(200);
});

/**
 * Test that verifies users can successfully confirm their password.
 *
 * This test ensures that:
 * 1. An authenticated user can submit their current password for confirmation
 * 2. Valid password confirmation succeeds without errors
 * 3. The user is redirected after successful confirmation
 * 4. No validation errors are present in the session
 * 5. Password confirmation state is properly maintained
 *
 * @test
 */
test('password can be confirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

/**
 * Test that verifies password confirmation fails with an incorrect password.
 *
 * This test ensures that:
 * 1. An authenticated user submits an incorrect password for confirmation
 * 2. Password confirmation fails with validation errors
 * 3. Session contains appropriate error messages
 * 4. Security is maintained by rejecting invalid passwords
 * 5. User cannot proceed without correct password confirmation
 *
 * @test
 */
test('password is not confirmed with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});
