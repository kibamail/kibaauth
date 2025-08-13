<?php

use App\Models\User;

/**
 * Test that verifies the user profile page can be displayed for authenticated users.
 *
 * This test ensures that:
 * 1. An authenticated user can access the /profile route
 * 2. The profile page renders successfully
 * 3. Returns a successful HTTP 200 status code
 * 4. User profile functionality is accessible to logged-in users
 *
 * @test
 */
test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

/**
 * Test that verifies user profile information can be successfully updated.
 *
 * This test ensures that:
 * 1. An authenticated user can update their profile information
 * 2. Email address can be changed via PATCH request to /profile
 * 3. The update request is processed without validation errors
 * 4. The user is redirected back to the profile page
 * 5. The new email address is saved to the database
 * 6. Email verification status is reset when email changes
 *
 * @test
 */
test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

/**
 * Test that verifies email verification status remains unchanged when email address is not modified.
 *
 * This test ensures that:
 * 1. An authenticated user can update their profile with the same email address
 * 2. The profile update request is processed successfully
 * 3. No validation errors occur when email remains the same
 * 4. The user is redirected back to the profile page
 * 5. The email verification status (email_verified_at) is preserved
 * 6. Users don't lose verification status for non-email updates
 *
 * @test
 */
test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

/**
 * Test that verifies users can successfully delete their own account.
 *
 * This test ensures that:
 * 1. An authenticated user can submit a delete request for their account
 * 2. The correct password must be provided for account deletion
 * 3. The deletion request is processed without validation errors
 * 4. The user is redirected to the home page after deletion
 * 5. The user is logged out (becomes guest) after account deletion
 * 6. The user record is completely removed from the database
 *
 * @test
 */
test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

/**
 * Test that verifies account deletion fails when incorrect password is provided.
 *
 * This test ensures that:
 * 1. An authenticated user attempts to delete their account
 * 2. An incorrect password is provided for verification
 * 3. The deletion request fails with validation errors
 * 4. Specific error is returned for the 'password' field
 * 5. The user is redirected back to the profile page
 * 6. The user account remains intact and is not deleted
 * 7. Security is maintained by requiring correct password verification
 *
 * @test
 */
test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
