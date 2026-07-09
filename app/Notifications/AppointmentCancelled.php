<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentCancelled extends Notification
{
    use Queueable;

    public function __construct(
        private Appointment $appointment,
        private string $reasonForCustomer,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;

        return (new MailMessage)
            ->subject('نوبت شما لغو شد - کد رهگیری '.$appointment->code)
            ->greeting('سلام '.$notifiable->name)
            ->line('نوبت شما با کد رهگیری '.$appointment->code.' لغو شد.')
            ->line('دلیل: '.$this->reasonForCustomer)
            ->line('در صورت هرگونه سؤال با پشتیبانی کلینیک تماس بگیرید.');
    }

    public function toSms(object $notifiable): array
    {
        return [
            'template' => 'appointment-cancel',
            'tokens' => [$this->appointment->code],
        ];
    }
}