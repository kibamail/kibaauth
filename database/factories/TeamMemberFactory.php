<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'email' => null,
            'status' => $this->faker->randomElement(['active', 'pending']),
        ];
    }

    /**
     * Indicate that the team member is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the team member is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the team member is email-only (no user account yet).
     */
    public function emailOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'email' => $this->faker->email(),
        ]);
    }

    /**
     * Set a specific email for the team member.
     */
    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'email' => $email,
        ]);
    }
}
