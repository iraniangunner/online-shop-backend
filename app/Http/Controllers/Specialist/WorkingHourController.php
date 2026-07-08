<?php

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkingHourController extends Controller
{
    public function index(Request $request)
    {
        $specialistId = $request->user()->specialist_id;

        return response()->json(
            WorkingHour::where('specialist_id', $specialistId)
                ->orderBy('day_of_week')
                ->get()
        );
    }

    /**
     * جایگزینی کامل ساعات کاری متخصص برای یک شعبه‌ی مشخص.
     * ورودی نمونه:
     * {
     *   "branch_id": 1,
     *   "hours": [
     *     {"day_of_week": 6, "start_time": "09:00", "end_time": "14:00"},
     *     {"day_of_week": 0, "start_time": "09:00", "end_time": "14:00"}
     *   ]
     * }
     */
    public function replace(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'required|exists:branches,id',
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|integer|min:0|max:6',
            'hours.*.start_time' => 'required|date_format:H:i',
            'hours.*.end_time' => 'required|date_format:H:i|after:hours.*.start_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $specialistId = $request->user()->specialist_id;

        DB::transaction(function () use ($request, $specialistId) {
            WorkingHour::where('specialist_id', $specialistId)
                ->where('branch_id', $request->branch_id)
                ->delete();

            foreach ($request->hours as $hour) {
                WorkingHour::create([
                    'specialist_id' => $specialistId,
                    'branch_id' => $request->branch_id,
                    'day_of_week' => $hour['day_of_week'],
                    'start_time' => $hour['start_time'],
                    'end_time' => $hour['end_time'],
                ]);
            }
        });

        return response()->json(['message' => 'ساعات کاری به‌روزرسانی شد.']);
    }
}