<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    public function store(Request $request, Appointment $appointment)
    {
        if ($appointment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'این نوبت متعلق به شما نیست.'], 403);
        }

        if ($appointment->status !== Appointment::STATUS_COMPLETED) {
            return response()->json(['message' => 'فقط بعد از انجام نوبت می‌توانید نظر ثبت کنید.'], 422);
        }

        if ($appointment->review()->exists()) {
            return response()->json(['message' => 'برای این نوبت قبلاً نظر ثبت شده است.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $review = $appointment->review()->create([
            'user_id' => $request->user()->id,
            'specialist_id' => $appointment->specialist_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'نظر شما ثبت شد و پس از تأیید ادمین نمایش داده می‌شود.',
            'review' => $review,
        ], 201);
    }
}