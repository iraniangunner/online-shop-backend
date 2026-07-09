<?php

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * لیست نوبت‌های خودِ متخصص لاگین‌شده.
     */
    public function index(Request $request)
    {
        $specialistId = $request->user()->specialist_id;

        if (! $specialistId) {
            return response()->json(['message' => 'این کاربر به هیچ متخصصی وصل نیست.'], 403);
        }

        $query = Appointment::with(['user', 'services', 'branch'])
            ->where('specialist_id', $specialistId)
            ->orderBy('starts_at');

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->date);
        } else {
            // پیش‌فرض: فقط نوبت‌های آینده
            $query->where('starts_at', '>=', now()->startOfDay());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * تغییر وضعیت نوبت توسط متخصص (تأیید حضور، انجام‌شده، عدم حضور).
     * متخصص فقط می‌تواند نوبت‌های خودش را تغییر دهد.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        if ($appointment->specialist_id !== $request->user()->specialist_id) {
            return response()->json(['message' => 'این نوبت متعلق به شما نیست.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:completed,no_show,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = ['status' => $request->status];

        if ($request->status === Appointment::STATUS_CANCELLED) {
            $data['cancelled_at'] = now();
            $data['cancel_reason'] = 'لغو توسط متخصص';
        }

        $appointment->update($data);

        if ($request->status === Appointment::STATUS_CANCELLED) {
            $appointment->user->notify(new \App\Notifications\AppointmentCancelled(
                $appointment,
                'متخصص شما در این ساعت در دسترس نیست. لطفاً نوبت جدیدی رزرو کنید.'
            ));
        }

        return response()->json(['message' => 'وضعیت نوبت به‌روزرسانی شد.', 'appointment' => $appointment]);
    }
}