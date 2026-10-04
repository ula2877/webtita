<?php

namespace App\Services;

use App\Exports\ArrearExport;
use App\Models\Arrear;
use Illuminate\Support\Collection;

class ExcelExportService
{
    public function buildQuery(?int $periodId, ?int $petugasId, ?string $status, ?string $hasilKunjungan): Collection
    {
        $query = Arrear::query()
            ->with(['customer', 'petugas', 'visit'])
            ->orderByDesc('id');

        if ($periodId) {
            $query->where('period_id', $periodId);
        }
        if ($petugasId) {
            $query->where('petugas_id', $petugasId);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($hasilKunjungan) {
            $query->whereHas('visit', fn ($q) => $q->where('status_kunjungan', $hasilKunjungan));
        }

        return $query->get();
    }

    public function download(Collection $arrears, string $fileName)
    {
        return (new ArrearExport($arrears))->download($fileName);
    }
}
