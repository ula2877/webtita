<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_sambungan',
        'nama',
        'wilayah_id',
        'address',
    ];

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }

    public function arrears(): HasMany
    {
        return $this->hasMany(Arrear::class);
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Arrear::class);
    }
}
