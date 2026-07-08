<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * ثبت‌نام مشتری جدید.
     * توجه: ثبت‌نام عمومی همیشه نقش customer می‌گیرد.
     * حساب متخصص/ادمین فقط توسط ادمین از پنل مدیریت ساخته می‌شود (کنترلر Admin\SpecialistController).
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'رمز عبور و تکرار آن مطابقت ندارند.',
            'password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'phone.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'email.email' => 'فرمت ایمیل صحیح نیست.',
            'name.required' => 'وارد کردن نام الزامی است.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'role' => User::ROLE_CUSTOMER,
        ]);

        return response()->json([
            'message' => 'ثبت‌نام با موفقیت انجام شد.',
            'user' => $user,
        ], 201);
    }

    /**
     * لاگین و گرفتن توکن با Password Grant.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'ایمیل یا رمز عبور اشتباه است.'], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return response()->json(['message' => 'حساب کاربری شما غیرفعال شده است.'], 403);
        }

        $response = Http::asForm()->post(url('/oauth/token'), [
            'grant_type' => 'password',
            'client_id' => config('services.passport.password_client_id'),
            'client_secret' => config('services.passport.password_client_secret'),
            'username' => $request->email,
            'password' => $request->password,
            'scope' => '',
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'خطا در احراز هویت. تنظیمات Passport Client رو چک کن.'], 401);
        }

        return response()->json(array_merge($response->json(), [
            'user' => $user,
        ]));
    }

    /**
     * رفرش کردن توکن.
     */
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $response = Http::asForm()->post(url('/oauth/token'), [
            'grant_type' => 'refresh_token',
            'refresh_token' => $request->refresh_token,
            'client_id' => config('services.passport.password_client_id'),
            'client_secret' => config('services.passport.password_client_secret'),
            'scope' => '',
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'توکن رفرش معتبر نیست.'], 401);
        }

        return response()->json($response->json());
    }

    /**
     * لاگ‌اوت و باطل کردن توکن فعلی.
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json(['message' => 'خروج با موفقیت انجام شد.']);
    }

    /**
     * گرفتن اطلاعات کاربر لاگین‌شده.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
