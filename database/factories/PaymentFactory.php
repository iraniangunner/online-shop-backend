<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'amount' => fake()->numberBetween(100_000, 1_000_000),
            'gateway' => 'zarinpal',
            'status' => Payment::STATUS_PENDING,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => Payment::STATUS_PAID,
            'ref_id' => (string) fake()->numberBetween(100000, 999999),
            'paid_at' => now(),
        ]);
    }
}