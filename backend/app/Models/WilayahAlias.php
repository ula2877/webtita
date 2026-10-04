<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WilayahAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'wilayah_id',
        'alias',
    ];

    public function wilayah(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class);
    }
}