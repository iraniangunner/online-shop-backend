<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+10 days');

        return [
            'user_id' => User::factory(),
            'specialist_id' => Specialist::factory(),
            'branch_id' => Branch::factory(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+30 minutes'),
            'status' => Appointment::STATUS_CONFIRMED,
            'total_price' => fake()->numberBetween(100_000, 1_000_000),
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => ['status' => Appointment::STATUS_PENDING_PAYMENT]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Appointment::STATUS_COMPLETED,
            'starts_at' => fake()->dateTimeBetween('-10 days', '-1 day'),
            'ends_at' => now()->subDays(1),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Appointment::STATUS_CANCELLED]);
    }
}