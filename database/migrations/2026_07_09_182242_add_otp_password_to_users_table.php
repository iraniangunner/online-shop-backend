<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // این ستون کاملاً جدا از password واقعی کاربره؛ فقط برای گرفتن
            // توکن از مسیر Password Grant موقع ورود با OTP استفاده می‌شه،
            // و هیچ‌وقت password اصلی کاربر رو دست‌کاری نمی‌کنه.
            $table->string('otp_password')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('otp_password');
        });
    }
};