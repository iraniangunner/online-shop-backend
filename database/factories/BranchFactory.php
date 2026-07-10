<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => 'شعبه‌ی '.fake()->city(),
            'phone' => '021'.fake()->numerify('########'),
            'address' => fake()->address(),
            'city' => 'تهران',
            'is_active' => true,
        ];
    }
}