<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{
    public function index()
    {
        return response()->json(Branch::orderBy('name')->paginate(20));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $branch = Branch::create($validator->validated());

        return response()->json(['message' => 'شعبه با موفقیت ساخته شد.', 'branch' => $branch], 201);
    }

    public function show(Branch $branch)
    {
        return response()->json($branch->load('specialists', 'services'));
    }

    public function update(Request $request, Branch $branch)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $branch->update($validator->validated());

        return response()->json(['message' => 'شعبه ویرایش شد.', 'branch' => $branch]);
    }

    public function destroy(Branch $branch)
    {
        // به‌جای حذف واقعی، غیرفعال کردن رو پیشنهاد می‌کنم چون appointments قدیمی بهش وصلن
        $branch->update(['is_active' => false]);

        return response()->json(['message' => 'شعبه غیرفعال شد.']);
    }
}
