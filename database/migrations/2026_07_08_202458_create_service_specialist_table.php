<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مشخص می‌کند هر متخصص کدام خدمات را در کدام شعبه ارائه می‌دهد
        Schema::create('service_specialist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'specialist_id', 'branch_id'], 'service_specialist_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_specialist');
    }
};
