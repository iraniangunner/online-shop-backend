<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;
    private Specialist $specialist;
    private Branch $branch;
    private Service $service30min;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AvailabilityService();
        $this->branch = Branch::factory()->create();
        $this->specialist = Specialist::factory()->create();
        $this->specialist->branches()->attach($this->branch->id);

        $this->service30min = Service::factory()->create(['duration_minutes' => 30]);
        $this->specialist->services()->attach($this->service30min->id, ['branch_id' => $this->branch->id]);
    }

    public function test_it_returns_no_slots_when_specialist_has_no_working_hours(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $slots = $this->service->getAvailableSlots(
            $this->specialist->id,
            $this->branch->id,
            $tomorrow,
            [$this->service30min->id]
        );

        $this->assertEmpty($slots);
    }

    public function test_it_returns_slots_within_working_hours(): void
    {
        $date = Carbon::tomorrow();

        WorkingHour::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $slots = $this->service->getAvailableSlots(
            $this->specialist->id,
            $this->branch->id,
            $date->toDateString(),
            [$this->service30min->id]
        );

        // بین ۹ تا ۱۰ (۶۰ دقیقه)، با خدمت ۳۰ دقیقه‌ای و گام ۱۵ دقیقه‌ای،
        // باید 09:00 و 09:15 و 09:30 در دسترس باشن (09:30+30=10:00 دقیقاً می‌گنجه)
        $this->assertContains('09:00', $slots);
        $this->assertContains('09:15', $slots);
        $this->assertContains('09:30', $slots);
        // 09:45 + 30 دقیقه = 10:15 که از بازه‌ی کاری بیرونه
        $this->assertNotContains('09:45', $slots);
    }

    public function test_it_excludes_slots_that_overlap_with_existing_appointments(): void
    {
        $date = Carbon::tomorrow();

        WorkingHour::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $user = User::factory()->create();

        // یه نوبت تأییدشده از ۰۹:۳۰ تا ۱۰:۰۰ می‌سازیم
        Appointment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'user_id' => $user->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'starts_at' => $date->copy()->setTime(9, 30),
            'ends_at' => $date->copy()->setTime(10, 0),
        ]);

        $slots = $this->service->getAvailableSlots(
            $this->specialist->id,
            $this->branch->id,
            $date->toDateString(),
            [$this->service30min->id]
        );

        // 09:30 دقیقاً روی نوبت موجوده - نباید باشه
        $this->assertNotContains('09:30', $slots);
        // 09:00 قبل از نوبته و کامل جا می‌شه (09:00-09:30) - باید باشه
        $this->assertContains('09:00', $slots);
        // 10:00 بعد از پایان نوبته - باید باشه
        $this->assertContains('10:00', $slots);
    }

    public function test_it_excludes_slots_during_time_off(): void
    {
        $date = Carbon::tomorrow();

        WorkingHour::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);

        // کل روز مرخصیه
        TimeOff::factory()->create([
            'specialist_id' => $this->specialist->id,
            'starts_at' => $date->copy()->setTime(0, 0),
            'ends_at' => $date->copy()->setTime(23, 59),
        ]);

        $slots = $this->service->getAvailableSlots(
            $this->specialist->id,
            $this->branch->id,
            $date->toDateString(),
            [$this->service30min->id]
        );

        $this->assertEmpty($slots);
    }

    public function test_it_sums_duration_of_multiple_services(): void
    {
        $date = Carbon::tomorrow();

        WorkingHour::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'day_of_week' => $date->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $service2 = Service::factory()->create(['duration_minutes' => 45]);
        $this->specialist->services()->attach($service2->id, ['branch_id' => $this->branch->id]);

        // مجموع دو خدمت = 30 + 45 = 75 دقیقه، ولی بازه‌ی کاری فقط 60 دقیقه‌ست
        $slots = $this->service->getAvailableSlots(
            $this->specialist->id,
            $this->branch->id,
            $date->toDateString(),
            [$this->service30min->id, $service2->id]
        );

        $this->assertEmpty($slots);
    }

    public function test_is_slot_still_available_returns_false_when_conflicting_appointment_exists(): void
    {
        $date = Carbon::tomorrow();
        $user = User::factory()->create();

        Appointment::factory()->create([
            'specialist_id' => $this->specialist->id,
            'branch_id' => $this->branch->id,
            'user_id' => $user->id,
            'status' => Appointment::STATUS_CONFIRMED,
            'starts_at' => $date->copy()->setTime(9, 0),
            'ends_at' => $date->copy()->setTime(9, 30),
        ]);

        $isAvailable = $this->service->isSlotStillAvailable(
            $this->specialist->id,
            $date->copy()->setTime(9, 15),
            $date->copy()->setTime(9, 45)
        );

        $this->assertFalse($isAvailable);
    }

    public function test_is_slot_still_available_returns_true_when_no_conflict(): void
    {
        $date = Carbon::tomorrow();

        $isAvailable = $this->service->isSlotStillAvailable(
            $this->specialist->id,
            $date->copy()->setTime(9, 0),
            $date->copy()->setTime(9, 30)
        );

        $this->assertTrue($isAvailable);
    }
}
