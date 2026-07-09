<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use HasFactory;

    protected $fillable = ['mobile', 'code', 'used', 'expires_at'];

    protected $casts = [
        'used' => 'boolean',
        'expires_at' => 'datetime',
    ];
}
