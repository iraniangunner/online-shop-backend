<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\ZarinpalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(private ZarinpalService $zarinpal) {}

    /**
     * این endpoint عمداً public است (بدون auth:api).
     * چون خودِ authority (که زرین‌پال برمی‌گرداند) یک مقدار تصادفی و غیرقابل‌حدس است
     * و همان نقشِ کلید امنیتی برای شناسایی این تراکنش خاص را بازی می‌کند.
     * نیازی به بررسی توکن کاربر نیست - داشتن authority به‌تنهایی کافی است.
     * (این دقیقاً همان الگویی است که در verify() پروژه‌ی فروشگاهی هم استفاده شده.)
     *
     * POST /api/payments/verify
     * body: { "authority": "...", "status": "OK" | "NOK" }
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'authority' => 'required|string',
            'status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payment = Payment::where('authority', $request->authority)->first();

        if (! $payment) {
            return response()->json(['message' => 'تراکنش یافت نشد.'], 404);
        }

        // اگر قبلاً verify شده، دوباره پردازش نکن (کاربر ممکنه صفحه رو رفرش کنه)
        if ($payment->status === Payment::STATUS_PAID) {
            return response()->json([
                'message' => 'این پرداخت قبلاً تأیید شده است.',
                'appointment' => $payment->appointment->load('services'),
            ]);
        }

        $appointment = $payment->appointment;

        if ($request->status !== 'OK') {
            $payment->update(['status' => Payment::STATUS_FAILED]);
            $appointment->update(['status' => Appointment::STATUS_CANCELLED, 'cancel_reason' => 'عدم پرداخت']);

            return response()->json(['message' => 'پرداخت لغو شد یا ناموفق بود.'], 400);
        }

        $result = $this->zarinpal->verify($payment->amount, $request->authority);

        if (! $result['success']) {
            $payment->update(['status' => Payment::STATUS_FAILED]);

            return response()->json(['message' => $result['message']], 400);
        }

        // قفل کردن ردیف تا اگه verify دوبار هم‌زمان صدا زده بشه، فقط یک‌بار پردازش بشه
        DB::transaction(function () use ($payment, $appointment, $result) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($payment->status === Payment::STATUS_PAID) {
                return;
            }

            $payment->markAsPaid($result['ref_id']);
            $appointment->update(['status' => Appointment::STATUS_CONFIRMED]);
        });

        $appointment->refresh();
        $appointment->user->notify(new \App\Notifications\AppointmentConfirmed($appointment));

        $specialistUser = $appointment->specialist->user;
        if ($specialistUser) {
            $specialistUser->notify(new \App\Notifications\NewAppointmentBooked($appointment));
        }

        return response()->json([
            'message' => 'پرداخت با موفقیت انجام شد و نوبت شما تأیید شد.',
            'appointment' => $appointment->fresh()->load('services'),
            'ref_id' => $result['ref_id'],
        ]);
    }
}
