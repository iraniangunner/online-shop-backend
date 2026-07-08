<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SpecialistController extends Controller
{
    public function index()
    {
        return response()->json(Specialist::with('branches')->orderBy('full_name')->paginate(20));
    }

    /**
     * ساخت متخصص + (اختیاری) حساب کاربری برای ورود به پنل متخصص.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'exists:branches,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:services,id',

            // اگر می‌خواهیم متخصص خودش هم بتواند وارد پنل شود:
            'create_account' => 'boolean',
            'email' => 'required_if:create_account,true|nullable|email|unique:users,email',
            'password' => 'required_if:create_account,true|nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $specialist = DB::transaction(function () use ($request) {
            $specialist = Specialist::create([
                'full_name' => $request->full_name,
                'phone' => $request->phone,
                'bio' => $request->bio,
            ]);

            $specialist->branches()->sync($request->branch_ids);

            if ($request->filled('service_ids')) {
                // برای هر خدمت، در همه‌ی شعبی که متخصص در آن‌هاست ثبت می‌شود
                foreach ($request->service_ids as $serviceId) {
                    foreach ($request->branch_ids as $branchId) {
                        $specialist->services()->attach($serviceId, ['branch_id' => $branchId]);
                    }
                }
            }

            if ($request->boolean('create_account')) {
                User::create([
                    'name' => $request->full_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => $request->password,
                    'role' => User::ROLE_SPECIALIST,
                    'specialist_id' => $specialist->id,
                ]);
            }

            return $specialist;
        });

        return response()->json([
            'message' => 'متخصص با موفقیت ساخته شد.',
            'specialist' => $specialist->load('branches', 'services'),
        ], 201);
    }

    public function show(Specialist $specialist)
    {
        return response()->json($specialist->load('branches', 'services', 'workingHours', 'user'));
    }

    public function update(Request $request, Specialist $specialist)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'is_active' => 'boolean',
            'branch_ids' => 'sometimes|array|min:1',
            'branch_ids.*' => 'exists:branches,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $specialist->update($validator->safe()->except('branch_ids'));

        if ($request->has('branch_ids')) {
            $specialist->branches()->sync($request->branch_ids);
        }

        return response()->json(['message' => 'متخصص ویرایش شد.', 'specialist' => $specialist->load('branches')]);
    }

    public function destroy(Specialist $specialist)
    {
        $specialist->delete(); // soft delete

        return response()->json(['message' => 'متخصص حذف شد.']);
    }
}