<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0912'.fake()->unique()->numerify('#######'),
            'email_verified_at' => now(),
            'password' => 'password', // خودش هش می‌شه چون cast('password' => 'hashed') داریم
            'role' => User::ROLE_CUSTOMER,
            'phone_verified' => true,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMIN]);
    }

    public function specialistRole(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_SPECIALIST]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}