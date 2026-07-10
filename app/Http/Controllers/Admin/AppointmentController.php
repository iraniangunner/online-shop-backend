<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * لیست همه‌ی نوبت‌ها با فیلتر.
     */
    public function index(Request $request)
    {
        $query = Appointment::with(['user', 'specialist', 'branch', 'services'])
            ->orderByDesc('starts_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('specialist_id')) {
            $query->where('specialist_id', $request->specialist_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('starts_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('starts_at', '<=', $request->date_to);
        }

        return response()->json($query->paginate(20));
    }

    public function show(Appointment $appointment)
    {
        return response()->json($appointment->load(['user', 'specialist', 'branch', 'services', 'payments', 'review']));
    }

    /**
     * تغییر وضعیت نوبت توسط ادمین (مثلاً لغو دستی، تأیید مجدد و...).
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending_payment,confirmed,completed,cancelled,no_show',
            'admin_note' => 'nullable|string',
            'cancel_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = ['status' => $request->status, 'admin_note' => $request->admin_note];

        if ($request->status === Appointment::STATUS_CANCELLED) {
            $data['cancel_reason'] = $request->cancel_reason;
            $data['cancelled_at'] = now();
        }

        $appointment->update($data);

        if ($request->status === Appointment::STATUS_CANCELLED) {
            try {
                $appointment->user->notify(new \App\Notifications\AppointmentCancelled(
                    $appointment,
                    $request->cancel_reason ?: 'این نوبت توسط کلینیک لغو شد.'
                ));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('خطا در ارسال نوتیفیکیشن لغو نوبت', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['message' => 'وضعیت نوبت به‌روزرسانی شد.', 'appointment' => $appointment]);
    }
}
