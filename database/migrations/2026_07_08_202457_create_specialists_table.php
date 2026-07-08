<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialists', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes(); // برای حفظ سابقه‌ی نوبت‌های قدیمی
        });

        // یک متخصص می‌تواند در چند شعبه فعالیت کند
        Schema::create('branch_specialist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'specialist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_specialist');
        Schema::dropIfExists('specialists');
    }
};