<?php

/**
 * Test that verifies the basic testing framework functionality.
 *
 * This test ensures that:
 * 1. The Pest testing framework is properly configured
 * 2. Basic assertion methods are working correctly
 * 3. The test environment is set up properly
 *
 * @test
 */
test('that true is true', function () {
    expect(true)->toBeTrue();
});
