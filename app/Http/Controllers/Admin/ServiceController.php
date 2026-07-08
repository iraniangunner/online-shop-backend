<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('category')->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('service_category_id', $request->category_id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5',
            'price' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = Service::create([
            'service_category_id' => $request->service_category_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name).'-'.Str::random(4),
            'description' => $request->description,
            'duration_minutes' => $request->duration_minutes,
            'price' => $request->price,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $service->branches()->sync($request->branch_ids);

        return response()->json([
            'message' => 'خدمت با موفقیت ساخته شد.',
            'service' => $service->load('branches', 'category'),
        ], 201);
    }

    public function show(Service $service)
    {
        return response()->json($service->load('branches', 'category', 'specialists'));
    }

    public function update(Request $request, Service $service)
    {
        $validator = Validator::make($request->all(), [
            'service_category_id' => 'sometimes|required|exists:service_categories,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'sometimes|required|integer|min:5',
            'price' => 'sometimes|required|integer|min:0',
            'is_active' => 'boolean',
            'branch_ids' => 'sometimes|array|min:1',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service->update($validator->safe()->except('branch_ids'));

        if ($request->has('branch_ids')) {
            $service->branches()->sync($request->branch_ids);
        }

        return response()->json(['message' => 'خدمت ویرایش شد.', 'service' => $service->load('branches')]);
    }

    public function destroy(Service $service)
    {
        // soft delete: appointments قدیمی که به این خدمت وصلن خراب نمی‌شن
        $service->delete();

        return response()->json(['message' => 'خدمت حذف شد.']);
    }
}