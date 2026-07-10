<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvailabilityController extends Controller
{
    public function __construct(private AvailabilityService $availabilityService) {}

    /**
     * GET /available-slots?specialist_id=1&branch_id=1&date=2026-07-15&service_ids[]=3&service_ids[]=5
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'specialist_id' => 'required|exists:specialists,id',
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date|after_or_equal:today',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slots = $this->availabilityService->getAvailableSlots(
            specialistId: (int) $request->specialist_id,
            branchId: (int) $request->branch_id,
            date: $request->date,
            serviceIds: $request->service_ids,
        );

        return response()->json(['date' => $request->date, 'available_slots' => $slots]);
    }

    /**
     * GET /unavailable-days?specialist_id=1&branch_id=1&service_ids[]=3&start_date=2026-07-15&days=14
     * لیست روزهایی که هیچ ساعت خالی‌ای ندارن (مرخصی یا پرشده) رو برمی‌گردونه،
     * تا فرانت‌اند بتونه توی تقویم غیرفعالشون کنه.
     */
    public function unavailableDays(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'specialist_id' => 'required|exists:specialists,id',
            'branch_id' => 'required|exists:branches,id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'start_date' => 'required|date',
            'days' => 'nullable|integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $unavailableDates = $this->availabilityService->getUnavailableDates(
            specialistId: (int) $request->specialist_id,
            branchId: (int) $request->branch_id,
            serviceIds: $request->service_ids,
            startDate: $request->start_date,
            days: (int) ($request->days ?? 14),
        );

        return response()->json(['unavailable_dates' => $unavailableDates]);
    }
}