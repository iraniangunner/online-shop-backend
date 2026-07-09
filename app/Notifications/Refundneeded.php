<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundNeeded extends Notification
{
    use Queueable;

    public function __construct(private Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->payment->appointment;

        return (new MailMessage)
            ->subject('نیاز به استرداد وجه دستی - نوبت ' . $appointment->code)
            ->greeting('سلام ' . $notifiable->name)
            ->line('مشتری نوبت پرداخت‌شده‌ی خود را لغو کرده و باید مبلغ آن به‌صورت دستی از پنل زرین‌پال استرداد شود.')
            ->line('کد نوبت: ' . $appointment->code)
            ->line('مبلغ: ' . number_format($this->payment->amount) . ' تومان')
            ->line('کد پیگیری تراکنش (ref_id): ' . ($this->payment->ref_id ?? 'ثبت نشده'))
            ->line('بعد از انجام ریفاند در پنل زرین‌پال، این پرداخت را در بخش ادمین سیستم به‌عنوان "ریفاند شد" علامت بزنید.');
    }
}
