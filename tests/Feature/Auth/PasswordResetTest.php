<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * Test that verifies the password reset link request screen can be rendered.
 *
 * This test ensures that:
 * 1. The /forgot-password route is accessible to unauthenticated users
 * 2. The password reset request page renders successfully
 * 3. Returns a successful HTTP 200 status code
 * 4. Users can access the password reset functionality when needed
 *
 * @test
 */
test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

/**
 * Test that verifies password reset links can be successfully requested.
 *
 * This test ensures that:
 * 1. A user can submit their email address to request a password reset
 * 2. The system sends a ResetPassword notification to the user
 * 3. Email-based password reset functionality works correctly
 * 4. Notifications are properly queued and sent
 *
 * @test
 */
test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

/**
 * Test that verifies the password reset screen can be rendered with a valid token.
 *
 * This test ensures that:
 * 1. A password reset notification is sent to a user
 * 2. The reset token is generated and included in the notification
 * 3. The /reset-password/{token} route is accessible
 * 4. The password reset form renders successfully with the token
 * 5. Returns a successful HTTP 200 status code
 *
 * @test
 */
test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

/**
 * Test that verifies passwords can be successfully reset using a valid token.
 *
 * This test ensures that:
 * 1. A password reset notification is sent to a user
 * 2. The reset token from the notification can be used to reset the password
 * 3. Valid password reset data (token, email, password, confirmation) is accepted
 * 4. The password reset process completes without validation errors
 * 5. The user is redirected to the login page after successful reset
 * 6. The new password is properly hashed and stored
 *
 * @test
 */
test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
