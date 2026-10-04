<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wilayah extends Model
{
    use HasFactory;

    protected $table = 'wilayah';

    protected $fillable = [
        'code',
        'name',
        'petugas_id',
    ];

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(WilayahAlias::class);
    }
}