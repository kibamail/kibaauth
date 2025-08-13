<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Test that verifies authenticated users can successfully update their password.
 *
 * This test ensures that:
 * 1. An authenticated user can access the password update functionality
 * 2. Valid current password, new password, and confirmation are accepted
 * 3. The password update request is processed without validation errors
 * 4. The user is redirected back to the profile page
 * 5. The new password is properly hashed and stored in the database
 * 6. The old password is replaced with the new one
 *
 * @test
 */
test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

/**
 * Test that verifies password update fails when incorrect current password is provided.
 *
 * This test ensures that:
 * 1. An authenticated user attempts to update their password
 * 2. An incorrect current password is provided for verification
 * 3. The password update request fails with validation errors
 * 4. Specific error is returned for the 'current_password' field
 * 5. The user is redirected back to the profile page
 * 6. Security is maintained by requiring correct current password verification
 *
 * @test
 */
test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');
});
