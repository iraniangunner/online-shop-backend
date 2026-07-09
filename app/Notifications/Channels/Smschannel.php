<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsService $smsService) {}

    /**
     * لاراول این متد رو خودکار صدا می‌زنه اگه Notification توی via()
     * کلاس این Channel رو برگردونده باشه.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $mobile = $notifiable->phone ?? null;

        if (! $mobile) {
            return; // کاربر شماره موبایل نداره، نادیده بگیر
        }

        $data = $notification->toSms($notifiable);

        $this->smsService->sendByTemplate(
            mobile: $mobile,
            template: $data['template'],
            tokens: $data['tokens'],
        );
    }
}
