<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Specialist $specialist;
    private Service $service;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->specialist = Specialist::factory()->create();
        $this->specialist->branches()->attach($this->branch->id);

        $this->service = Service::factory()->create(['duration_minutes' => 30, 'price' => 500000]);
        $this->specialist->services()->attach($this->service->id, ['branch_id' => $this->branch->id]);

        // متخصص هر روز از ۹ تا ۱۷ کار می‌کنه (برای همه‌ی روزهای هفته، تا مستقل از تاریخ امروز باشه)
        foreach (range(0, 6) as $day) {
            WorkingHour::factory()->create([
                'specialist_id' => $this->specialist->id,
                'branch_id' => $this->branch->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
            ]);
        }

        $this->customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        // هیچ ایمیل/پیامک واقعی موقع تست فرستاده نشه
        Notification::fake();

        // درخواست‌های واقعی به زرین‌پال رو fake می‌کنیم
        Http::fake([
            '*zarinpal.com*' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => 'A00000000000000000000000000000000000',
                ],
            ], 200),
        ]);
    }

    public function test_customer_can_view_available_slots(): void
    {
        Passport::actingAs($this->customer);

        $date = Carbon::tomorrow()->toDateString();

        $response = $this->getJson(
            "/api/available-slots?specialist_id={$this->specialist->id}&branch_id={$this->branch->id}&date={$date}&service_ids[]={$this->service->id}"
        );

        $response->assertStatus(200)
            ->assertJsonStructure(['date', 'available_slots']);

        $this->assertNotEmpty($response->json('available_slots'));
    }

    public function test_customer_can_create_an_appointment(): void
    {
        Passport::actingAs($this->customer);

        $date = Carbon::tomorrow()->toDateString();

        $response = $this->postJson('/api/appointments', [
            'branch_id' => $this->branch->id,
            'specialist_id' => $this->specialist->id,
            'service_ids' => [$this->service->id],
            'date' => $date,
            'time' => '10:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'appointment', 'payment_url']);

        $this->assertDatabaseHas('appointments', [
            'user_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'status' => Appointment::STATUS_PENDING_PAYMENT,
            'total_price' => 500000,
        ]);

        $appointment = Appointment::where('user_id', $this->customer->id)->first();
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'amount' => 500000,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    public function test_booking_the_same_slot_twice_returns_conflict(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        Passport::actingAs($this->customer);
        $firstResponse = $this->postJson('/api/appointments', [
            'branch_id' => $this->branch->id,
            'specialist_id' => $this->specialist->id,
            'service_ids' => [$this->service->id],
            'date' => $date,
            'time' => '10:00',
        ]);
        $firstResponse->assertStatus(201);

        // مشتری دوم دقیقاً همون ساعت رو می‌خواد رزرو کنه
        /** @var User $secondCustomer */
        $secondCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        Passport::actingAs($secondCustomer);

        $secondResponse = $this->postJson('/api/appointments', [
            'branch_id' => $this->branch->id,
            'specialist_id' => $this->specialist->id,
            'service_ids' => [$this->service->id],
            'date' => $date,
            'time' => '10:00',
        ]);

        $secondResponse->assertStatus(409);
    }

    public function test_booking_in_the_past_is_rejected(): void
    {
        Passport::actingAs($this->customer);

        $response = $this->postJson('/api/appointments', [
            'branch_id' => $this->branch->id,
            'specialist_id' => $this->specialist->id,
            'service_ids' => [$this->service->id],
            'date' => Carbon::yesterday()->toDateString(),
            'time' => '10:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_create_appointment(): void
    {
        $response = $this->postJson('/api/appointments', [
            'branch_id' => $this->branch->id,
            'specialist_id' => $this->specialist->id,
            'service_ids' => [$this->service->id],
            'date' => Carbon::tomorrow()->toDateString(),
            'time' => '10:00',
        ]);

        $response->assertStatus(401);
    }

    public function test_customer_can_cancel_their_own_upcoming_appointment(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addMinutes(30),
        ]);

        Passport::actingAs($this->customer);

        $response = $this->postJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CANCELLED,
        ]);
    }

    public function test_customer_cannot_cancel_someone_elses_appointment(): void
    {
        $otherCustomer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $appointment = Appointment::factory()->create([
            'user_id' => $otherCustomer->id,
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addMinutes(30),
        ]);

        Passport::actingAs($this->customer);

        $response = $this->postJson("/api/appointments/{$appointment->id}/cancel");

        $response->assertStatus(403);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_cancelling_a_paid_appointment_marks_payment_as_refund_pending(): void
    {
        $appointment = Appointment::factory()->create([
            'user_id' => $this->customer->id,
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'starts_at' => now()->addDays(3), // خیلی جلوتر، پس داخل بازه‌ی مجاز لغو
            'ends_at' => now()->addDays(3)->addMinutes(30),
        ]);

        $payment = $appointment->payments()->create([
            'amount' => 500000,
            'gateway' => 'zarinpal',
            'status' => Payment::STATUS_PAID,
            'ref_id' => 'TEST-REF',
            'paid_at' => now(),
        ]);

        Passport::actingAs($this->customer);

        $this->postJson("/api/appointments/{$appointment->id}/cancel")->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_REFUND_PENDING,
        ]);
    }
}
