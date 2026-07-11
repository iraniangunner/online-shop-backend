<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $this->admin = $admin;
    }

    private function makeAppointmentWithPendingRefund(): Payment
    {
        $branch = Branch::factory()->create();
        $specialist = Specialist::factory()->create();
        $customer = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'user_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'branch_id' => $branch->id,
            'status' => Appointment::STATUS_CANCELLED,
        ]);

        return $appointment->payments()->create([
            'amount' => 500000,
            'gateway' => 'zarinpal',
            'status' => Payment::STATUS_REFUND_PENDING,
            'ref_id' => 'TEST-REF-123',
        ]);
    }

    public function test_admin_can_see_pending_refunds_list(): void
    {
        $this->makeAppointmentWithPendingRefund();

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/admin/payments/pending-refunds');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_paid_payments_do_not_appear_in_pending_refunds(): void
    {
        $branch = Branch::factory()->create();
        $specialist = Specialist::factory()->create();
        $customer = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'user_id' => $customer->id,
            'specialist_id' => $specialist->id,
            'branch_id' => $branch->id,
        ]);

        $appointment->payments()->create([
            'amount' => 500000,
            'gateway' => 'zarinpal',
            'status' => Payment::STATUS_PAID,
        ]);

        Passport::actingAs($this->admin);

        $response = $this->getJson('/api/admin/payments/pending-refunds');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_admin_can_mark_a_refund_as_completed(): void
    {
        $payment = $this->makeAppointmentWithPendingRefund();

        Passport::actingAs($this->admin);

        $response = $this->patchJson("/api/admin/payments/{$payment->id}/mark-refunded");

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUNDED,
        ]);
    }

    public function test_cannot_mark_an_already_refunded_payment_again(): void
    {
        $payment = $this->makeAppointmentWithPendingRefund();
        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        Passport::actingAs($this->admin);

        $response = $this->patchJson("/api/admin/payments/{$payment->id}/mark-refunded");

        $response->assertStatus(422);
    }

    public function test_customer_cannot_access_refund_management(): void
    {
        $payment = $this->makeAppointmentWithPendingRefund();

        /** @var User $customer */
        $customer = User::factory()->create();
        Passport::actingAs($customer);

        $response = $this->patchJson("/api/admin/payments/{$payment->id}/mark-refunded");

        $response->assertStatus(403);
    }
}
