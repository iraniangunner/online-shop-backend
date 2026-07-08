<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * ثبت‌نام کاربر جدید (اختیاری - اگه لازم داری)
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'رمز عبور و تکرار آن مطابقت ندارند.',
            'password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'email.unique' => 'این ایمیل قبلاً ثبت شده است.',
            'email.email' => 'فرمت ایمیل صحیح نیست.',
            'name.required' => 'وارد کردن نام الزامی است.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
    }
    /**
     * لاگین و گرفتن توکن با Password Grant
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

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
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
            return response()->json(['message' => 'Could not authenticate'], 401);
        }

        return response()->json($response->json());
    }

    /**
     * رفرش کردن توکن
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
            return response()->json(['message' => 'Could not refresh token'], 401);
        }

        return response()->json($response->json());
    }

    /**
     * لاگ‌اوت و باطل کردن توکن فعلی
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * گرفتن اطلاعات کاربر لاگین‌شده (تست میدل‌ور)
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
