<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // در MySQL برای تغییر enum باید کل ستون رو دوباره تعریف کنیم
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending', 'paid', 'failed', 'refund_pending', 'refunded') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending'");
    }
};
