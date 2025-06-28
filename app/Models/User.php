<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $fillable = ['telegram_id', 'name', 'balance', 'wallet', 'ref_count', 'is_active', 'is_withdrawal'];
    protected $casts = [
        'is_active' => 'boolean',
        'is_withdrawal' => 'boolean',
    ];
}
