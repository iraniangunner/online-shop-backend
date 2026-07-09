<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAppointmentBooked extends Notification
{
    use Queueable;

    public function __construct(private Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment->load('services', 'user');
        $servicesList = $appointment->services->pluck('name')->implode('، ');

        return (new MailMessage)
            ->subject('نوبت جدید ثبت شد - '.$appointment->starts_at->format('Y-m-d H:i'))
            ->greeting('سلام '.$notifiable->name)
            ->line('یک نوبت جدید برای شما ثبت و تأیید شد.')
            ->line('مشتری: '.$appointment->user->name)
            ->line('خدمات: '.$servicesList)
            ->line('تاریخ و ساعت: '.$appointment->starts_at->format('Y-m-d H:i'));
    }
}