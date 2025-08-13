<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

/**
 * Test that verifies the email verification screen can be rendered for unverified users.
 *
 * This test ensures that:
 * 1. An unverified user can be created using the factory
 * 2. The /verify-email route is accessible to authenticated users
 * 3. The email verification page renders successfully
 * 4. Returns a successful HTTP 200 status code
 *
 * @test
 */
test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

/**
 * Test that verifies email addresses can be successfully verified using a valid verification link.
 *
 * This test ensures that:
 * 1. An unverified user can be created
 * 2. A temporary signed verification URL can be generated with the correct parameters
 * 3. The verification URL contains the user ID and email hash
 * 4. Accessing the verification URL triggers the Verified event
 * 5. The user's email is marked as verified in the database
 * 6. The user is redirected to the dashboard with a verified parameter
 *
 * @test
 */
test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

/**
 * Test that verifies email verification fails with an invalid hash.
 *
 * This test ensures that:
 * 1. An unverified user can be created
 * 2. A verification URL can be generated with an incorrect email hash
 * 3. Accessing the invalid verification URL does not verify the email
 * 4. The user's email remains unverified for security purposes
 * 5. Invalid verification attempts are properly rejected
 *
 * @test
 */
test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
