<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'mobile' => fake()->unique()->numerify('09#########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile_verified_at' => now(),
            'email_verified_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['mobile_verified_at' => null]);
    }
}
