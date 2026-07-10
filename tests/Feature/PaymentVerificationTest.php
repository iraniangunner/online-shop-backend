<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Appointment $appointment;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $branch = Branch::factory()->create();
        $specialist = Specialist::factory()->create();
        $customer = User::factory()->create();

        $this->appointment = Appointment::factory()->create([
            'user_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'branch_id' => $branch->id,
            'status' => Appointment::STATUS_PENDING_PAYMENT,
            'total_price' => 500000,
        ]);

        $this->payment = $this->appointment->payments()->create([
            'amount' => 500000,
            'gateway' => 'zarinpal',
            'status' => Payment::STATUS_PENDING,
            'authority' => 'A00000000000000000000000000000000000',
        ]);
    }

    public function test_successful_verification_confirms_appointment(): void
    {
        Http::fake([
            '*zarinpal.com*' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 123456789],
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/verify', [
            'authority' => $this->payment->authority,
            'status' => 'OK',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => Payment::STATUS_PAID,
            'ref_id' => '123456789',
        ]);
    }

    public function test_verification_does_not_require_authentication(): void
    {
        // عمداً هیچ کاربری لاگین نکردیم - چون authority خودش کلید امنیتیه
        Http::fake([
            '*zarinpal.com*' => Http::response(['data' => ['code' => 100, 'ref_id' => 111]], 200),
        ]);

        $response = $this->postJson('/api/payments/verify', [
            'authority' => $this->payment->authority,
            'status' => 'OK',
        ]);

        $response->assertStatus(200);
    }

    public function test_verifying_twice_does_not_process_payment_twice(): void
    {
        Http::fake([
            '*zarinpal.com*' => Http::response(['data' => ['code' => 100, 'ref_id' => 999]], 200),
        ]);

        $this->postJson('/api/payments/verify', [
            'authority' => $this->payment->authority,
            'status' => 'OK',
        ])->assertStatus(200);

        // بار دوم - نباید دوباره zarinpal رو صدا بزنه یا وضعیت رو خراب کنه
        $response = $this->postJson('/api/payments/verify', [
            'authority' => $this->payment->authority,
            'status' => 'OK',
        ]);

        $response->assertStatus(200);

        // هنوز فقط یه پرداخت با ref_id درسته، وضعیت paid مونده
        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => Payment::STATUS_PAID,
            'ref_id' => '999',
        ]);
    }

    public function test_cancelled_payment_from_gateway_cancels_appointment(): void
    {
        $response = $this->postJson('/api/payments/verify', [
            'authority' => $this->payment->authority,
            'status' => 'NOK', // یعنی کاربر توی درگاه انصراف داده
        ]);

        $response->assertStatus(400);

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);
    }

    public function test_unknown_authority_returns_not_found(): void
    {
        $response = $this->postJson('/api/payments/verify', [
            'authority' => 'THIS_AUTHORITY_DOES_NOT_EXIST',
            'status' => 'OK',
        ]);

        $response->assertStatus(404);
    }

    public function test_failed_gateway_verification_marks_payment_as_failed(): void
    {
        Http::fake([
            '*zarinpal.com*' => Http::response([
                'errors' => ['message' => 'تراکنش تأیید نشد'],
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/verify', [
            'authority' => $this->payment->authority,
            'status' => 'OK',
        ]);

        $response->assertStatus(400);

        $this->assertDatabaseHas('payments', [
            'id' => $this->payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        // نوبت نباید تأیید بشه
        $this->assertDatabaseMissing('appointments', [
            'id' => $this->appointment->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
    }
}