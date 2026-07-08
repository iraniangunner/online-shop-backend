<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * لیست خدمات فعال یک شعبه، به تفکیک دسته‌بندی.
     */
    public function index(Request $request)
    {
        $query = Service::active()->with('category');

        if ($request->filled('branch_id')) {
            $query->whereHas('branches', function ($q) use ($request) {
                $q->where('branches.id', $request->branch_id);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('service_category_id', $request->category_id);
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function show(Service $service)
    {
        if (! $service->is_active) {
            return response()->json(['message' => 'خدمت یافت نشد.'], 404);
        }

        return response()->json($service->load('category', 'branches'));
    }

    /**
     * متخصص‌هایی که این خدمت را در یک شعبه‌ی مشخص ارائه می‌دهند.
     */
    public function specialists(Request $request, Service $service)
    {
        $request->validate(['branch_id' => 'required|exists:branches,id']);

        $specialists = $service->specialistsInBranch($request->branch_id)
            ->where('is_active', true)
            ->get();

        return response()->json($specialists);
    }
}