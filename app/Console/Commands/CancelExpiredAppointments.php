<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Payment;
use App\Notifications\AppointmentCancelled;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelExpiredAppointments extends Command
{
    private const EXPIRY_MINUTES = 15;

    protected $signature = 'appointments:cancel-expired';

    protected $description = 'لغو خودکار نوبت‌هایی که پس از مهلت مشخص، پرداخت نشده‌اند';

    public function handle(): int
    {
        $expiredAppointments = Appointment::where('status', Appointment::STATUS_PENDING_PAYMENT)
            ->where('created_at', '<=', now()->subMinutes(self::EXPIRY_MINUTES))
            ->get();

        if ($expiredAppointments->isEmpty()) {
            $this->info('نوبت منقضی‌شده‌ای برای لغو وجود ندارد.');

            return self::SUCCESS;
        }

        foreach ($expiredAppointments as $appointment) {
            try {
                DB::transaction(function () use ($appointment) {
                    $appointment = Appointment::where('id', $appointment->id)
                        ->lockForUpdate()
                        ->first();

                    if ($appointment->status !== Appointment::STATUS_PENDING_PAYMENT) {
                        return;
                    }

                    $appointment->update([
                        'status' => Appointment::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                        'cancel_reason' => 'عدم پرداخت در مهلت مقرر',
                    ]);

                    $appointment->payments()
                        ->where('status', Payment::STATUS_PENDING)
                        ->update(['status' => Payment::STATUS_FAILED]);
                });

                $appointment->refresh();

                if ($appointment->status === Appointment::STATUS_CANCELLED) {
                    $appointment->user->notify(new AppointmentCancelled(
                        $appointment,
                        'مهلت پرداخت این نوبت به پایان رسید و به‌صورت خودکار لغو شد.'
                    ));
                }
            } catch (\Throwable $e) {
                Log::error('خطا در لغو خودکار نوبت', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($expiredAppointments->count().' نوبت منقضی‌شده لغو شد.');

        return self::SUCCESS;
    }
}