<?php

use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\BranchController as AdminBranchController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SpecialistController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer\AppointmentController as CustomerAppointmentController;
use App\Http\Controllers\Customer\AvailabilityController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Public\BranchController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\Specialist\AppointmentController as SpecialistAppointmentController;
use App\Http\Controllers\Specialist\TimeOffController;
use App\Http\Controllers\Specialist\WorkingHourController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (عمومی)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| مسیرهای عمومی (بدون نیاز به لاگین) - مشاهده شعبه و خدمات
|--------------------------------------------------------------------------
*/
Route::get('/branches', [BranchController::class, 'index']);
Route::get('/branches/{branch}', [BranchController::class, 'show']);

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::get('/services/{service}/specialists', [ServiceController::class, 'specialists']);
Route::get('/specialists', [\App\Http\Controllers\Public\SpecialistController::class, 'index']);

// Payment verify — عمداً public است، چون خودِ authority نقش کلید امنیتی رو داره
Route::post('/payments/verify', [PaymentController::class, 'verify']);

/*
|--------------------------------------------------------------------------
| پنل ادمین
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('branches', AdminBranchController::class);
    Route::apiResource('service-categories', ServiceCategoryController::class)->except(['show']);
    Route::apiResource('services', AdminServiceController::class);
    Route::apiResource('specialists', SpecialistController::class);

    Route::get('appointments', [AdminAppointmentController::class, 'index']);
    Route::get('appointments/{appointment}', [AdminAppointmentController::class, 'show']);
    Route::patch('appointments/{appointment}/status', [AdminAppointmentController::class, 'updateStatus']);

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive']);

    Route::get('reviews', [AdminReviewController::class, 'index']);
    Route::patch('reviews/{review}/approve', [AdminReviewController::class, 'approve']);
    Route::delete('reviews/{review}', [AdminReviewController::class, 'destroy']);

    Route::get('payments/pending-refunds', [\App\Http\Controllers\Admin\PaymentController::class, 'pendingRefunds']);
    Route::patch('payments/{payment}/mark-refunded', [\App\Http\Controllers\Admin\PaymentController::class, 'markRefunded']);
});

/*
|--------------------------------------------------------------------------
| پنل متخصص
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'role:specialist'])->prefix('specialist')->group(function () {
    Route::get('appointments', [SpecialistAppointmentController::class, 'index']);
    Route::patch('appointments/{appointment}/status', [SpecialistAppointmentController::class, 'updateStatus']);

    Route::get('working-hours', [WorkingHourController::class, 'index']);
    Route::put('working-hours', [WorkingHourController::class, 'replace']);

    Route::get('time-off', [TimeOffController::class, 'index']);
    Route::post('time-off', [TimeOffController::class, 'store']);
    Route::delete('time-off/{timeOff}', [TimeOffController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| پنل مشتری (رزرو نوبت، پرداخت، نظرات)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'role:customer'])->group(function () {
    Route::get('available-slots', [AvailabilityController::class, 'index']);
    Route::get('unavailable-days', [AvailabilityController::class, 'unavailableDays']);

    Route::get('appointments', [CustomerAppointmentController::class, 'index']);
    Route::post('appointments', [CustomerAppointmentController::class, 'store']);
    Route::get('appointments/{appointment}', [CustomerAppointmentController::class, 'show']);
    Route::post('appointments/{appointment}/cancel', [CustomerAppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/review', [CustomerReviewController::class, 'store']);
});