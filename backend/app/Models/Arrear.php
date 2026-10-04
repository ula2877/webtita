<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Arrear extends Model
{
    use HasFactory;

    public const STATUS_BELUM_DIKUNJUNGI = 'belum_dikunjungi';
    public const STATUS_SUDAH_DIKUNJUNGI = 'sudah_dikunjungi';

    protected $fillable = [
        'customer_id',
        'period_id',
        'jumlah_bulan_tunggakan',
        'jumlah_tagihan',
        'petugas_id',
        'status',
        'foto_bukti',
    ];

    protected $casts = [
        'jumlah_bulan_tunggakan' => 'integer',
        'jumlah_tagihan' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class, 'arrears_id');
    }
}
