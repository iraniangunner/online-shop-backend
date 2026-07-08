<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // ۱ تا ۵
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false); // نیاز به تأیید ادمین
            $table->timestamps();

            $table->unique('appointment_id'); // هر نوبت فقط یک نظر
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
