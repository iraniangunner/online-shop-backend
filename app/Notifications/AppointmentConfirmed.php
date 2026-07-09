<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentConfirmed extends Notification
{
    use Queueable;

    public function __construct(private Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->load('services', 'specialist', 'branch');
        $servicesList = $appointment->services->pluck('name')->implode('، ');

        return (new MailMessage)
            ->subject('نوبت شما تأیید شد - کد رهگیری '.$appointment->code)
            ->greeting('سلام '.$notifiable->name)
            ->line('پرداخت شما با موفقیت انجام شد و نوبت‌تان تأیید شد.')
            ->line('کد رهگیری: '.$appointment->code)
            ->line('خدمات: '.$servicesList)
            ->line('متخصص: '.$appointment->specialist->full_name)
            ->line('شعبه: '.$appointment->branch->name)
            ->line('تاریخ و ساعت: '.$appointment->starts_at->format('Y-m-d H:i'))
            ->line('مبلغ پرداخت‌شده: '.number_format($appointment->total_price).' تومان')
            ->line('لطفاً ۱۰ دقیقه زودتر از زمان نوبت در شعبه حضور داشته باشید.');
    }

    /**
     * داده‌ی لازم برای ارسال پیامک با الگوی 'appointment-confirm'.
     * توجه: توکن‌های کاوه‌نگار فقط حروف انگلیسی/عدد قبول می‌کنن (بدون فارسی، بدون فاصله)،
     * برای همین فقط کد رهگیری رو می‌فرستیم؛ جزئیات کامل (اسم، تاریخ) توی ایمیل هست.
     */
    public function toSms(object $notifiable): array
    {
        return [
            'template' => 'appointment-confirm',
            'tokens' => [$this->appointment->code],
        ];
    }
}