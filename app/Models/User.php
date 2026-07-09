<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_SPECIALIST = 'specialist';
    public const ROLE_ADMIN = 'admin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'otp_password',
        'role',
        'phone',
        'phone_verified',
        'is_active',
        'specialist_id',
    ];

    protected $hidden = [
        'password',
        'otp_password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'otp_password' => 'hashed',
            'phone_verified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // اگر کاربر نقش متخصص داشته باشد، به رکورد Specialist وصل می‌شود
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSpecialist(): bool
    {
        return $this->role === self::ROLE_SPECIALIST;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    /**
     * Passport v13 موقع Password Grant این متد رو با ۲ پارامتر صدا می‌زنه
     * (username و خودِ client). به‌جای جستجوی پیش‌فرض روی email، این‌جا
     * هم با ایمیل هم با موبایل می‌شه لاگین کرد.
     */
    public function findForPassport(string $username, \Laravel\Passport\Bridge\Client $client): ?self
    {
        return self::where('email', $username)
            ->orWhere('phone', $username)
            ->first();
    }

    /**
     * Passport موقع Password Grant به‌جای Hash::check پیش‌فرض روی password،
     * این متد رو صدا می‌زنه (اگه وجود داشته باشه). این‌طوری هم پسورد واقعی
     * (برای ورود با ایمیل) هم otp_password موقت (برای ورود با OTP) قبول می‌شه،
     * بدون اینکه پسورد واقعی کاربر هیچ‌وقت دست‌کاری بشه.
     */
    public function validateForPassportPasswordGrant(string $password): bool
    {
        if ($this->password && \Illuminate\Support\Facades\Hash::check($password, $this->password)) {
            return true;
        }

        if ($this->otp_password && \Illuminate\Support\Facades\Hash::check($password, $this->otp_password)) {
            return true;
        }

        return false;
    }

    /**
     * به‌جای ایمیل پیش‌فرض لاراول (که به یه route وب اشاره می‌کنه)،
     * از Notification سفارشی خودمون استفاده می‌کنیم که به فرانت‌اند Next.js وصله.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
