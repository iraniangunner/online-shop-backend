<?php

namespace Database\Factories;

use App\Models\Specialist;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeOffFactory extends Factory
{
    protected $model = TimeOff::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+5 days');

        return [
            'specialist_id' => Specialist::factory(),
            'branch_id' => null,
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+2 hours'),
            'reason' => 'مرخصی استعلاجی',
        ];
    }
}