<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specialist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone',
        'bio',
        'avatar',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // حساب کاربری متخصص برای لاگین (اگر متخصص نیاز به ورود به پنل داشته باشد)
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_specialist');
    }

    // خدماتی که این متخصص ارائه می‌دهد (با شعبه‌ی مربوطه در pivot)
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_specialist')
            ->withPivot('branch_id')
            ->withTimestamps();
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    public function timeOff(): HasMany
    {
        return $this->hasMany(TimeOff::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // میانگین امتیاز از نظرات تأییدشده
    public function averageRating(): float
    {
        return (float) $this->reviews()->where('is_approved', true)->avg('rating');
    }
}
