<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    use HasFactory;

    public const HASIL_ADA_ORANG = 'ada_orang';
    public const HASIL_RUMAH_KOSONG = 'rumah_kosong';
    public const HASIL_TIDAK_ADA_ORANG = 'tidak_ada_orang';
    public const HASIL_LAINNYA = 'lainnya';

    protected $fillable = [
        'arrears_id',
        'petugas_id',
        'status_kunjungan',
        'keterangan',
        'foto_bukti',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function arrears(): BelongsTo
    {
        return $this->belongsTo(Arrear::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}
