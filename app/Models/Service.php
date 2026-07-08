<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'service_category_id', 'name', 'slug', 'description',
        'image', 'duration_minutes', 'price', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'integer',
        'duration_minutes' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_service');
    }

    // متخصص‌هایی که این خدمت را ارائه می‌دهند (با شعبه‌ی مربوطه در pivot)
    public function specialists(): BelongsToMany
    {
        return $this->belongsToMany(Specialist::class, 'service_specialist')
            ->withPivot('branch_id')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // متخصص‌های این خدمت، فیلترشده برای یک شعبه‌ی خاص
    public function specialistsInBranch(int $branchId)
    {
        return $this->specialists()->wherePivot('branch_id', $branchId);
    }
}