<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Specialist;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkingHourFactory extends Factory
{
    protected $model = WorkingHour::class;

    public function definition(): array
    {
        return [
            'specialist_id' => Specialist::factory(),
            'branch_id' => Branch::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ];
    }
}