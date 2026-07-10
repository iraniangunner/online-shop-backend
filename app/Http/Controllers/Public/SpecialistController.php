<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use Illuminate\Http\Request;

class SpecialistController extends Controller
{
    /**
     * متخصص‌هایی که همه‌ی خدمات انتخابی رو (نه فقط یکی‌شون) در یک شعبه‌ی
     * مشخص ارائه می‌دهند. برای رزرو چند خدمت همزمان استفاده می‌شه.
     *
     * GET /specialists?branch_id=1&service_ids[]=3&service_ids[]=5
     */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
        ]);

        $branchId = (int) $request->branch_id;
        $requestedServiceIds = collect($request->service_ids)->map(fn($id) => (int) $id);

        $specialists = Specialist::query()
            ->active()
            ->whereHas('branches', fn($q) => $q->where('branches.id', $branchId))
            ->get()
            ->filter(function (Specialist $specialist) use ($branchId, $requestedServiceIds) {
                $providedServiceIds = $specialist->services()
                    ->wherePivot('branch_id', $branchId)
                    ->pluck('services.id');

                // متخصص باید همه‌ی خدمات درخواستی رو پوشش بده، نه فقط بخشی‌شون
                return $requestedServiceIds->diff($providedServiceIds)->isEmpty();
            })
            ->values();

        return response()->json($specialists);
    }
}
