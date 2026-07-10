<?php

namespace Database\Factories;

use App\Models\Specialist;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpecialistFactory extends Factory
{
    protected $model = Specialist::class;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => '0912'.fake()->numerify('#######'),
            'bio' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}