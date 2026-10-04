<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_PETUGAS = 'petugas';

    protected $fillable = [
        'name',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isPetugas(): bool
    {
        return $this->role === self::ROLE_PETUGAS;
    }

    public function arrears(): HasMany
    {
        return $this->hasMany(Arrear::class, 'petugas_id');
    }

    public function wilayah(): HasMany
    {
        return $this->hasMany(Wilayah::class, 'petugas_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'petugas_id');
    }
}
