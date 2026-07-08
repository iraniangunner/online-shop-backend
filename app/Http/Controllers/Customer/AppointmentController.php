<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\ZarinpalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private ZarinpalService $zarinpal,
    ) {}

    /**
     * لیست نوبت‌های خودِ مشتری.
     */
    public function index(Request $request)
    {
        $appointments = Appointment::with(['specialist', 'branch', 'services'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('starts_at')
            ->paginate(20);

        return response()->json($appointments);
    }

    public function show(Request $request, Appointment $appointment)
    {
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'این نوبت متعلق به شما نیست.'], 403);
        }

        return response()->json($appointment->load(['specialist', 'branch', 'services', 'payments']));
    }

    /**
     * ثبت نوبت جدید.
     * ورودی:
     * {
     *   "branch_id": 1,
     *   "specialist_id": 2,
     *   "service_ids": [3, 5],
     *   "date": "2026-07-15",
     *   "time": "10:30"
     * }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'specialist_id' => 'required|exists:specialists,id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $services = Service::whereIn('id', $request->service_ids)->get();

        if ($services->count() !== count($request->service_ids)) {
            return response()->json(['message' => 'یک یا چند خدمت انتخابی معتبر نیست.'], 422);
        }

        $totalDuration = $services->sum('duration_minutes');
        $totalPrice = $services->sum('price');

        $startsAt = Carbon::parse("{$request->date} {$request->time}");
        $endsAt = $startsAt->copy()->addMinutes($totalDuration);

        if ($startsAt->isPast()) {
            return response()->json(['message' => 'زمان انتخابی گذشته است.'], 422);
        }

        try {
            $appointment = DB::transaction(function () use (
                $request, $services, $startsAt, $endsAt, $totalPrice
            ) {
                // بررسی نهایی خالی بودن اسلات، این‌بار با لاک روی ردیف‌ها
                // تا اگر دو نفر هم‌زمان همین ثانیه درخواست بدهند، فقط یکی موفق شود.
                if (! $this->availabilityService->isSlotStillAvailable(
                    (int) $request->specialist_id, $startsAt, $endsAt
                )) {
                    abort(409, 'این ساعت لحظاتی پیش توسط شخص دیگری رزرو شد. لطفاً ساعت دیگری انتخاب کنید.');
                }

                $appointment = Appointment::create([
                    'user_id' => $request->user()->id,
                    'specialist_id' => $request->specialist_id,
                    'branch_id' => $request->branch_id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => Appointment::STATUS_PENDING_PAYMENT,
                    'total_price' => $totalPrice,
                ]);

                foreach ($services as $service) {
                    $appointment->services()->attach($service->id, [
                        'price_at_booking' => $service->price,
                        'duration_minutes_at_booking' => $service->duration_minutes,
                    ]);
                }

                return $appointment;
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'خطا در ثبت نوبت. لطفاً دوباره تلاش کنید.'], 500);
        }

        // ساخت رکورد پرداخت و اتصال به درگاه
        $payment = $appointment->payments()->create([
            'amount' => $totalPrice,
            'gateway' => 'zarinpal',
            'status' => 'pending',
        ]);

        $gatewayResponse = $this->zarinpal->request(
            amount: $totalPrice,
            // callback_url ثابته و از config میاد (نیازی به payment_id توی URL نیست،
            // چون زرین‌پال خودش Authority رو به این آدرس اضافه می‌کنه و از روی
            // همون Authority می‌شه پرداخت رو پیدا کرد - دقیقاً مثل transaction لوکاپ).
            callbackUrl: config('services.zarinpal.callback_url'),
            description: "پرداخت نوبت {$appointment->code}",
            mobile: $request->user()->phone ?? '',
        );

        if (! $gatewayResponse['success']) {
            return response()->json([
                'message' => $gatewayResponse['message'],
                'appointment' => $appointment,
            ], 502);
        }

        $payment->update(['authority' => $gatewayResponse['authority']]);

        return response()->json([
            'message' => 'نوبت شما ثبت شد، برای تکمیل به درگاه پرداخت هدایت شوید.',
            'appointment' => $appointment->load('services'),
            'payment_url' => $gatewayResponse['payment_url'],
        ], 201);
    }

    /**
     * لغو نوبت توسط مشتری.
     */
    public function cancel(Request $request, Appointment $appointment)
    {
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'این نوبت متعلق به شما نیست.'], 403);
        }

        if (! $appointment->isCancellable()) {
            return response()->json(['message' => 'این نوبت قابل لغو نیست.'], 422);
        }

        $withinFreeCancellation = $appointment->isWithinFreeCancellationWindow();

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => 'لغو توسط مشتری',
        ]);

        // TODO: اگر پرداخت انجام شده و withinFreeCancellation === true،
        // اینجا باید درخواست استرداد وجه (Refund) به درگاه ارسال شود.

        return response()->json([
            'message' => $withinFreeCancellation
                ? 'نوبت لغو شد. مبلغ پرداختی طی ۷۲ ساعت بازگردانده می‌شود.'
                : 'نوبت لغو شد. طبق قوانین لغو کمتر از ۲۴ ساعت، مبلغ بازگردانده نمی‌شود.',
        ]);
    }
}