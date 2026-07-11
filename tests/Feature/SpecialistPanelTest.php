<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Specialist;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SpecialistPanelTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Specialist $specialist;
    private User $specialistUser;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->branch = Branch::factory()->create();
        $this->specialist = Specialist::factory()->create();
        $this->specialist->branches()->attach($this->branch->id);

        $this->specialistUser = User::factory()->specialistRole()->create([
            'specialist_id' => $this->specialist->id,
        ]);

        Passport::actingAs($this->specialistUser);
    }

    public function test_specialist_can_see_only_their_own_appointments(): void
    {
        $customer = User::factory()->create();

        $ownAppointment = Appointment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'user_id' => $customer->id,
        ]);

        $otherSpecialist = Specialist::factory()->create();
        Appointment::factory()->create([
            'specialist_id' => $otherSpecialist->id,
            'branch_id' => $this->branch->id,
            'user_id' => $customer->id,
        ]);

        $response = $this->getJson('/api/specialist/appointments?date=' . $ownAppointment->starts_at->toDateString());

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($ownAppointment->id));
    }

    public function test_specialist_can_mark_own_appointment_as_completed(): void
    {
        $customer = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'user_id' => $customer->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $response = $this->patchJson("/api/specialist/appointments/{$appointment->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_COMPLETED,
        ]);
    }

    public function test_specialist_cannot_update_someone_elses_appointment(): void
    {
        $otherSpecialist = Specialist::factory()->create();
        $customer = User::factory()->create();

        $appointment = Appointment::factory()->create([
            'specialist_id' => $otherSpecialist->id,
            'branch_id' => $this->branch->id,
            'user_id' => $customer->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);

        $response = $this->patchJson("/api/specialist/appointments/{$appointment->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
    }

    public function test_specialist_can_set_working_hours(): void
    {
        $response = $this->putJson('/api/specialist/working-hours', [
            'branch_id' => $this->branch->id,
            'hours' => [
                ['day_of_week' => 6, 'start_time' => '09:00', 'end_time' => '17:00'],
                ['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '17:00'],
            ],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('working_hours', [
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => 6,
        ]);

        $this->assertEquals(
            2,
            WorkingHour::where('specialist_id', $this->specialist->id)->count()
        );
    }

    public function test_setting_working_hours_replaces_old_ones(): void
    {
        WorkingHour::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => 1,
        ]);

        $response = $this->putJson('/api/specialist/working-hours', [
            'branch_id' => $this->branch->id,
            'hours' => [
                ['day_of_week' => 6, 'start_time' => '10:00', 'end_time' => '14:00'],
            ],
        ]);

        $response->assertStatus(200);

        // روزِ ۱ (دوشنبه) قبلی باید پاک شده باشه
        $this->assertDatabaseMissing('working_hours', [
            'specialist_id' => $this->specialist->id,
            'day_of_week' => 1,
        ]);

        $this->assertDatabaseHas('working_hours', [
            'specialist_id' => $this->specialist->id,
            'day_of_week' => 6,
        ]);
    }

    public function test_working_hours_with_invalid_time_format_fails(): void
    {
        $response = $this->putJson('/api/specialist/working-hours', [
            'branch_id' => $this->branch->id,
            'hours' => [
                ['day_of_week' => 6, 'start_time' => 'not-a-time', 'end_time' => '17:00'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_specialist_can_create_time_off(): void
    {
        $response = $this->postJson('/api/specialist/time-off', [
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHours(2)->toDateTimeString(),
            'reason' => 'مرخصی استعلاجی',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('time_off', [
            'specialist_id' => $this->specialist->id,
            'reason' => 'مرخصی استعلاجی',
        ]);
    }

    public function test_time_off_in_the_past_is_rejected(): void
    {
        $response = $this->postJson('/api/specialist/time-off', [
            'starts_at' => now()->subDay()->toDateTimeString(),
            'ends_at' => now()->subDay()->addHours(2)->toDateTimeString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_specialist_can_delete_own_time_off(): void
    {
        $timeOff = TimeOff::factory()->create(['specialist_id' => $this->specialist->id]);

        $response = $this->deleteJson("/api/specialist/time-off/{$timeOff->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('time_off', ['id' => $timeOff->id]);
    }

    public function test_specialist_cannot_delete_someone_elses_time_off(): void
    {
        $otherSpecialist = Specialist::factory()->create();
        $timeOff = TimeOff::factory()->create(['specialist_id' => $otherSpecialist->id]);

        $response = $this->deleteJson("/api/specialist/time-off/{$timeOff->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('time_off', ['id' => $timeOff->id]);
    }
}
