<?php

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Models\TimeOff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TimeOffController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            TimeOff::where('specialist_id', $request->user()->specialist_id)
                ->orderByDesc('starts_at')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch_id' => 'nullable|exists:branches,id',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $timeOff = TimeOff::create([
            'specialist_id' => $request->user()->specialist_id,
            'branch_id' => $request->branch_id,
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at,
            'reason' => $request->reason,
        ]);

        return response()->json(['message' => 'مرخصی ثبت شد.', 'time_off' => $timeOff], 201);
    }

    public function destroy(Request $request, TimeOff $timeOff)
    {
        if ($timeOff->specialist_id !== $request->user()->specialist_id) {
            return response()->json(['message' => 'این مرخصی متعلق به شما نیست.'], 403);
        }

        $timeOff->delete();

        return response()->json(['message' => 'مرخصی حذف شد.']);
    }
}