<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * لیست پرداخت‌هایی که منتظر ریفاند دستی هستن.
     */
    public function pendingRefunds()
    {
        $payments = Payment::with('appointment.user')
            ->where('status', Payment::STATUS_REFUND_PENDING)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($payments);
    }

    /**
     * بعد از اینکه ادمین دستی از پنل زرین‌پال ریفاند کرد، اینجا تأییدش می‌کنه.
     */
    public function markRefunded(Payment $payment)
    {
        if ($payment->status !== Payment::STATUS_REFUND_PENDING) {
            return response()->json(['message' => 'این پرداخت در وضعیت انتظار ریفاند نیست.'], 422);
        }

        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        return response()->json(['message' => 'پرداخت به‌عنوان ریفاندشده علامت خورد.', 'payment' => $payment]);
    }
}
