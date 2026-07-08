<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique(); // کد رهگیری برای مشتری

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at'); // = starts_at + مجموع duration خدمات انتخابی

            $table->enum('status', [
                'pending_payment', // در انتظار پرداخت
                'confirmed',       // تأیید شده (پس از پرداخت موفق)
                'completed',       // انجام شد
                'cancelled',       // لغو شد
                'no_show',         // مشتری حضور نیافت
            ])->default('pending_payment');

            $table->unsignedBigInteger('total_price'); // مجموع قیمت خدمات، در لحظه‌ی ثبت
            $table->text('admin_note')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // جلوگیری از رزرو دو نوبت هم‌زمان برای یک متخصص در دیتابیس نمی‌آید،
            // این کنترل باید در منطق اپلیکیشن (Service Layer + لاک) انجام شود.
            $table->index(['specialist_id', 'starts_at', 'ends_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};