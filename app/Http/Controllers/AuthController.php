<?php

namespace App\Http\Controllers;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\SmsService;
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
            // مهم: پسورد رو خام بده، چون مدل User با cast('password' => 'hashed')
            // خودش موقع ذخیره هش می‌کنه. اگه اینجا هم bcrypt/Hash بزنی، دوبار هش می‌شه
            // و کاربر دیگه نمی‌تونه لاگین کنه.
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
     * ارسال کد یکبارمصرف برای ورود با موبایل.
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.regex' => 'فرمت شماره موبایل صحیح نیست (مثال: 09123456789).',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mobile = $request->mobile;

        // لایه ۱: حداقل ۲ دقیقه بین هر ارسال برای همون شماره
        $recentOtp = OtpCode::where('mobile', $mobile)
            ->where('created_at', '>', now()->subMinutes(2))
            ->first();

        if ($recentOtp) {
            $retryAfter = 120 - now()->diffInSeconds($recentOtp->created_at);

            return response()->json([
                'message' => 'لطفاً کمی صبر کنید و دوباره تلاش کنید.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        // لایه ۲: حداکثر ۵ بار در روز برای همون شماره
        $dailyCount = OtpCode::where('mobile', $mobile)
            ->where('created_at', '>', now()->startOfDay())
            ->count();

        if ($dailyCount >= 5) {
            return response()->json(['message' => 'سقف ارسال روزانه به پایان رسید. فردا دوباره تلاش کنید.'], 429);
        }

        // لایه ۳: حداکثر ۱۰ بار در ساعت از هر IP (جلوگیری از سوءاستفاده)
        $ipKey = 'otp_send_ip_'.$request->ip();
        $ipCount = cache()->get($ipKey, 0);

        if ($ipCount >= 10) {
            return response()->json(['message' => 'تعداد درخواست‌های شما بیش از حد مجاز است.'], 429);
        }

        $code = (string) rand(100000, 999999);

        $sent = app(SmsService::class)->sendByTemplate(
            mobile: $mobile,
            template: 'clinic-login-otp',
            tokens: [$code],
        );

        if (! $sent) {
            return response()->json(['message' => 'خطا در ارسال پیامک. لطفاً دوباره تلاش کنید.'], 500);
        }

        cache()->put($ipKey, $ipCount + 1, now()->addHour());

        OtpCode::create([
            'mobile' => $mobile,
            'code' => $code,
            'expires_at' => now()->addMinutes(5),
        ]);

        return response()->json([
            'message' => 'کد تأیید ارسال شد.',
            'expires_in' => 300,
        ]);
    }

    /**
     * تأیید کد و ورود/ثبت‌نام خودکار با موبایل.
     * اگه شماره قبلاً ثبت نشده باشه، یه حساب customer جدید خودکار ساخته می‌شه.
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mobile = $request->mobile;

        // rate limit تلاش‌های اشتباه
        $attemptsKey = 'otp_verify_attempts_'.$mobile;
        $attempts = cache()->get($attemptsKey, 0);

        if ($attempts >= 5) {
            return response()->json(['message' => 'تعداد تلاش‌های شما بیش از حد مجاز است. کد جدید بگیرید.'], 429);
        }

        $otpRecord = OtpCode::where('mobile', $mobile)
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otpRecord) {
            cache()->put($attemptsKey, $attempts + 1, now()->addMinutes(15));

            return response()->json(['message' => 'کد تأیید نامعتبر یا منقضی‌شده است.'], 401);
        }

        cache()->forget($attemptsKey);
        $otpRecord->update(['used' => true]);

        // پیدا کردن یا ساختن کاربر بر اساس موبایل
        $user = User::where('phone', $mobile)->first();
        $isNewUser = false;

        if (! $user) {
            $user = User::create([
                'name' => 'کاربر '.substr($mobile, -4),
                'phone' => $mobile,
                'phone_verified' => true,
                'email' => null,
                'password' => bin2hex(random_bytes(16)), // پسورد تصادفی، چون این کاربر با OTP لاگین می‌کنه نه پسورد
                'role' => User::ROLE_CUSTOMER,
            ]);
            $isNewUser = true;
        } elseif (! $user->phone_verified) {
            $user->update(['phone_verified' => true]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'حساب کاربری شما غیرفعال شده است.'], 403);
        }

        // یه پسورد تصادفی و یک‌بارمصرف توی ستون جداگانه‌ی otp_password می‌سازیم
        // (نه ستون password اصلی!) تا بتونیم از مسیر Password Grant توکن بگیریم،
        // بدون اینکه پسورد واقعی کاربر (اگه با ایمیل/پسورد هم ثبت‌نام کرده باشه) خراب بشه.
        $tempPassword = bin2hex(random_bytes(16));
        $user->update(['otp_password' => $tempPassword]);

        $response = Http::asForm()->post(url('/oauth/token'), [
            'grant_type' => 'password',
            'client_id' => config('services.passport.password_client_id'),
            'client_secret' => config('services.passport.password_client_secret'),
            'username' => $mobile,
            'password' => $tempPassword,
            'scope' => '',
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'خطا در صدور توکن. تنظیمات Passport Client رو چک کن.'], 401);
        }

        return response()->json(array_merge($response->json(), [
            'message' => $isNewUser ? 'ثبت‌نام و ورود با موفقیت انجام شد.' : 'ورود با موفقیت انجام شد.',
            'is_new_user' => $isNewUser,
            'user' => $user,
        ]));
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
     * درخواست ارسال لینک بازیابی رمز عبور.
     * عمداً پیام یکسان برمی‌گردونیم چه ایمیل وجود داشته باشه چه نه،
     * تا کسی نتونه با این endpoint بفهمه چه ایمیل‌هایی توی سیستم ثبت شدن.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'اگر این ایمیل در سیستم ثبت شده باشد، لینک بازیابی رمز عبور برایش ارسال شد.',
        ]);
    }

    /**
     * تنظیم رمز عبور جدید با استفاده از token ای که از ایمیل گرفته شده.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'رمز عبور و تکرار آن مطابقت ندارند.',
            'password.min' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // خام بده، چون cast('password' => 'hashed') خودش هش می‌کنه
                $user->update(['password' => $password]);
            }
        );

        if ($status !== \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'لینک بازیابی نامعتبر یا منقضی‌شده است.',
            ], 400);
        }

        return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد.']);
    }

    /**
     * گرفتن اطلاعات کاربر لاگین‌شده.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}