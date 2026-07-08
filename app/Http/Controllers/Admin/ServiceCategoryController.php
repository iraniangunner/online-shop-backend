<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        return response()->json(ServiceCategory::orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category = ServiceCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name).'-'.Str::random(4),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        return response()->json(['message' => 'دسته‌بندی ساخته شد.', 'category' => $category], 201);
    }

    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $serviceCategory->update($validator->validated());

        return response()->json(['message' => 'دسته‌بندی ویرایش شد.', 'category' => $serviceCategory]);
    }

    public function destroy(ServiceCategory $serviceCategory)
    {
        if ($serviceCategory->services()->exists()) {
            return response()->json(['message' => 'این دسته‌بندی خدمت دارد و قابل حذف نیست.'], 422);
        }

        $serviceCategory->delete();

        return response()->json(['message' => 'دسته‌بندی حذف شد.']);
    }
}