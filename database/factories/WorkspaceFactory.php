<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class WorkspaceFactory extends Factory
{

    protected $model = Workspace::class;


    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'user_id' => User::factory(),
            'client_id' => $this->faker->uuid,
        ];
    }


    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'slug' => Str::slug($name),
        ]);
    }


    public function withSlug(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug,
        ]);
    }


    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }


    public function forClient($client): static
    {
        $clientId = is_object($client) ? $client->id : $client;
        return $this->state(fn (array $attributes) => [
            'client_id' => $clientId,
        ]);
    }
}
