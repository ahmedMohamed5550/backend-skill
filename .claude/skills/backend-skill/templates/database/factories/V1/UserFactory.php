<?php

namespace Database\Factories\V1;

use App\Models\V1\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory for the V1 User model.
 *
 * Project models live in App\Models\V1, so project factories MUST live in
 * database/factories/V1 with the Database\Factories\V1 namespace — Laravel's
 * HasFactory convention resolves App\Models\V1\{Model} to
 * Database\Factories\V1\{Model}Factory.
 *
 * Never set `uuid` here: the Model's `creating` hook generates it.
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\V1\User>
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user's email address is not yet verified (the
     * Laravel-native unverified state, used by the email verification
     * tests and any business flow that needs a pending account).
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
