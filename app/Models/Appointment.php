<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Appointment extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'code', 'user_id', 'specialist_id', 'branch_id',
        'starts_at', 'ends_at', 'status', 'total_price',
        'admin_note', 'cancel_reason', 'cancelled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_price' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment) {
            if (empty($appointment->code)) {
                $appointment->code = self::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            // کد ۸ کاراکتری با حروف بزرگ + عدد، مثلاً: A3F9K2XQ
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // خدمات انتخاب‌شده در این نوبت، همراه با قیمت و مدت‌زمانِ لحظه‌ی ثبت
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_service')
            ->withPivot(['price_at_booking', 'duration_minutes_at_booking'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now())
            ->whereIn('status', [self::STATUS_CONFIRMED]);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_PAYMENT, self::STATUS_CONFIRMED])
            && $this->starts_at->isFuture();
    }

    // آیا مشتری هنوز داخل بازه‌ی مجاز بازگشت وجه است؟ (پیش‌فرض: ۲۴ ساعت قبل)
    public function isWithinFreeCancellationWindow(int $hours = 24): bool
    {
        return now()->diffInHours($this->starts_at, false) >= $hours;
    }
}
