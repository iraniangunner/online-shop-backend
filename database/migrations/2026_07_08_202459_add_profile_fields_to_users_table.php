<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // نقش کاربر در سیستم. متخصص و ادمین رکورد جدا هم دارند (specialists, admins)
            // ولی نقش اینجا برای کنترل دسترسی و Auth Guard لازم است.
            $table->enum('role', ['customer', 'specialist', 'admin'])
                ->default('customer')
                ->after('email');

            $table->string('phone')->nullable()->unique()->after('role');
            $table->boolean('phone_verified')->default(false)->after('phone');
            $table->boolean('is_active')->default(true)->after('phone_verified');

            // اگر این کاربر متخصص باشد، به رکورد specialists وصل می‌شود
            $table->foreignId('specialist_id')->nullable()
                ->after('is_active')
                ->constrained('specialists')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialist_id');
            $table->dropColumn(['role', 'phone', 'phone_verified', 'is_active']);
        });
    }
};
