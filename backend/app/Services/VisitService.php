<?php

namespace App\Services;

use App\Models\Arrear;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VisitService
{
    public function submit(Arrear $arrear, User $petugas, array $data): Visit
    {
        if ($arrear->petugas_id !== $petugas->id) {
            throw ValidationException::withMessages([
                'arrear_id' => ['Anda tidak memiliki akses ke tugas ini.'],
            ]);
        }

        $photoPath = null;
        if (! empty($data['foto_bukti'])) {
            $photoPath = $data['foto_bukti']->store('photos', 'public');
        }

        // Parse tanggal kunjungan from request
        $tanggalKunjungan = null;
        if (! empty($data['tanggal_kunjungan'])) {
            $tanggalKunjungan = \Illuminate\Support\Carbon::parse($data['tanggal_kunjungan']);
        }

        return DB::transaction(function () use ($arrear, $petugas, $data, $photoPath, $tanggalKunjungan) {
            // Storage::disk('public')->url() already returns full URL because disk config has 'url' => env('APP_URL').'/storage'
            $photoFullUrl = $photoPath ? Storage::disk('public')->url($photoPath) : null;

            $visit = Visit::updateOrCreate(
                ['arrears_id' => $arrear->id],
                [
                    'petugas_id' => $petugas->id,
                    'status_kunjungan' => $data['status_kunjungan'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'foto_bukti' => $photoFullUrl ?? $this->existingPhoto($arrear),
                    'visited_at' => $tanggalKunjungan ?? now(),
                ]
            );

            // Update arrears table with foto_bukti for direct access
            // Use $tanggalKunjungan for updated_at if provided, otherwise keep existing
            $arrear->update([
                'status' => Arrear::STATUS_SUDAH_DIKUNJUNGI,
                'foto_bukti' => $photoFullUrl ?? $arrear->foto_bukti,
                'updated_at' => $tanggalKunjungan ?? $arrear->updated_at,
            ], ['timestamps' => false]);

            app(AuditService::class)->log('petugas.visit', [
                'arrear_id' => $arrear->id,
                'visit_id' => $visit->id,
                'status_kunjungan' => $data['status_kunjungan'],
            ], $petugas->id);

            return $visit;
        });
    }

    private function existingPhoto(Arrear $arrear): ?string
    {
        return $arrear->visit?->foto_bukti;
    }
}
