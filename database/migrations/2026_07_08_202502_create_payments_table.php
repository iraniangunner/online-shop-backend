<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('gateway')->default('zarinpal'); // نام درگاه پرداخت
            $table->string('authority')->nullable();         // کد authority درگاه
            $table->string('ref_id')->nullable();             // کد پیگیری تراکنش موفق
            $table->enum('status', ['pending', 'paid', 'failed', 'refund_pending', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
