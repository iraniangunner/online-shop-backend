<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();

            // قیمت و مدت‌زمان در لحظه‌ی ثبت ذخیره می‌شود
            // (تا اگر بعداً قیمت خدمت در جدول services تغییر کرد، تاریخچه‌ی نوبت خراب نشود)
            $table->unsignedBigInteger('price_at_booking');
            $table->unsignedInteger('duration_minutes_at_booking');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_service');
    }
};