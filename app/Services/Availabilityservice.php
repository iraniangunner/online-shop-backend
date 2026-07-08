<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * فاصله‌ی بین اسلات‌های پیشنهادی (مثلاً هر ۱۵ دقیقه یک اسلات پیشنهاد می‌شود).
     * این با duration خدمت فرق دارد؛ duration مشخص می‌کند اسلات چقدر طول می‌کشد،
     * این عدد مشخص می‌کند هر چند دقیقه یک اسلات جدید امتحان شود.
     */
    private const SLOT_STEP_MINUTES = 15;

    /**
     * لیست ساعت‌های خالیِ یک متخصص در یک شعبه و یک تاریخ مشخص،
     * برای مجموع مدت‌زمانِ خدمات انتخابی.
     *
     * @param  array<int>  $serviceIds
     * @return array<string> لیست ساعت‌ها به فرمت H:i (مثلاً ["09:00", "09:15", ...])
     */
    public function getAvailableSlots(int $specialistId, int $branchId, string $date, array $serviceIds): array
    {
        $totalDuration = $this->calculateTotalDuration($serviceIds);

        if ($totalDuration <= 0) {
            return [];
        }

        $day = Carbon::parse($date);
        // Carbon: 0 = یکشنبه ... 6 = شنبه (مطابق چیزی که در working_hours ذخیره کردیم)
        $dayOfWeek = $day->dayOfWeek;

        $workingRanges = $this->getWorkingRanges($specialistId, $branchId, $dayOfWeek, $day);

        if ($workingRanges->isEmpty()) {
            return [];
        }

        $busyRanges = $this->getBusyRanges($specialistId, $day);

        $slots = [];

        foreach ($workingRanges as $range) {
            $cursor = $range['start']->copy();

            while ($cursor->copy()->addMinutes($totalDuration)->lte($range['end'])) {
                $candidateStart = $cursor->copy();
                $candidateEnd = $cursor->copy()->addMinutes($totalDuration);

                if (! $this->overlapsAny($candidateStart, $candidateEnd, $busyRanges)) {
                    $slots[] = $candidateStart->format('H:i');
                }

                $cursor->addMinutes(self::SLOT_STEP_MINUTES);
            }
        }

        return $slots;
    }

    private function calculateTotalDuration(array $serviceIds): int
    {
        return (int) Service::whereIn('id', $serviceIds)->sum('duration_minutes');
    }

    /**
     * بازه‌های کاری متخصص در آن روز هفته، به‌صورت Carbon datetime برای تاریخ مشخص.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function getWorkingRanges(int $specialistId, int $branchId, int $dayOfWeek, Carbon $date): Collection
    {
        return WorkingHour::where('specialist_id', $specialistId)
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->get()
            ->map(function (WorkingHour $wh) use ($date) {
                return [
                    'start' => $date->copy()->setTimeFromTimeString($wh->start_time),
                    'end' => $date->copy()->setTimeFromTimeString($wh->end_time),
                ];
            });
    }

    /**
     * بازه‌های اشغال‌شده: هم نوبت‌های ثبت‌شده (تأییدشده یا در انتظار پرداخت)
     * و هم مرخصی‌های آن روز.
     *
     * توجه: نوبت‌های pending_payment را هم مسدودکننده در نظر می‌گیریم تا دو نفر
     * هم‌زمان یک ساعت را رزرو نکنند؛ این نوبت‌ها باید بعد از X دقیقه عدم پرداخت
     * به‌صورت خودکار (مثلاً با یک Scheduled Job) لغو شوند - فاز بعدی.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function getBusyRanges(int $specialistId, Carbon $date): Collection
    {
        $appointments = Appointment::where('specialist_id', $specialistId)
            ->whereDate('starts_at', $date->toDateString())
            ->whereIn('status', [Appointment::STATUS_PENDING_PAYMENT, Appointment::STATUS_CONFIRMED])
            ->get()
            ->map(fn(Appointment $a) => ['start' => $a->starts_at->copy(), 'end' => $a->ends_at->copy()]);

        $timeOffs = TimeOff::where('specialist_id', $specialistId)
            ->whereDate('starts_at', '<=', $date->toDateString())
            ->whereDate('ends_at', '>=', $date->toDateString())
            ->get()
            ->map(fn(TimeOff $t) => ['start' => $t->starts_at->copy(), 'end' => $t->ends_at->copy()]);

        return $appointments->concat($timeOffs);
    }

    /**
     * آیا بازه‌ی [start, end) با یکی از بازه‌های busyRanges تداخل دارد؟
     *
     * @param  Collection<int, array{start: Carbon, end: Carbon}>  $busyRanges
     */
    private function overlapsAny(Carbon $start, Carbon $end, Collection $busyRanges): bool
    {
        foreach ($busyRanges as $busy) {
            // تداخل دو بازه: start1 < end2 AND start2 < end1
            if ($start->lt($busy['end']) && $busy['start']->lt($end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * بررسی این‌که آیا یک بازه‌ی زمانی مشخص (که کاربر انتخاب کرده) هنوز خالی است یا نه.
     * این متد داخل تراکنش با لاک استفاده می‌شود تا از رزرو هم‌زمان جلوگیری شود.
     */
    public function isSlotStillAvailable(int $specialistId, Carbon $startsAt, Carbon $endsAt): bool
    {
        $conflict = Appointment::where('specialist_id', $specialistId)
            ->whereIn('status', [Appointment::STATUS_PENDING_PAYMENT, Appointment::STATUS_CONFIRMED])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            return false;
        }

        $onLeave = TimeOff::where('specialist_id', $specialistId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        return ! $onLeave;
    }
}
