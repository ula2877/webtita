<?php

namespace App\Services;

class StrukturExcelImportService
{
    public function import(string $filePath, int $uploadedBy, ?string $originalFileName = null): array
    {
        $petugas = (new KetuaKelompokImportService())->import($filePath, $uploadedBy, $originalFileName);
        $wilayah = (new RekapWilayahImportService())->import($filePath, $uploadedBy, $originalFileName);

        // Pastikan alias wilayah (config) tersimpan di DB sebelum parsing detail tagihan.
        (new WilayahAliasService())->syncFromConfig();

        $detailTagihan = (new DetailTagihanImportService())->import($filePath, $uploadedBy, $originalFileName);

        return [
            'message' => 'Import berhasil.',
            'petugas' => $petugas,
            'wilayah' => $wilayah,
            'detail_tagihan' => $detailTagihan,
        ];
    }
}