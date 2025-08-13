<?php

/**
 * Test that verifies the login page returns a successful HTTP response.
 *
 * This test ensures that:
 * 1. The /login route is properly configured and accessible
 * 2. The login page renders without errors
 * 3. The HTTP status code is 200 (OK)
 *
 * @test
 */
it('returns a successful response', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
