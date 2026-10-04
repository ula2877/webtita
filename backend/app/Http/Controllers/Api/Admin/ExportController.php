<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function download(Request $request, ExcelExportService $service, AuditService $audit)
    {
        $periodId = $request->integer('period_id') ?: null;
        $petugasId = $request->integer('petugas_id') ?: null;
        $status = $request->filled('status') ? $request->status : null;
        $hasilKunjungan = $request->filled('hasil_kunjungan') ? $request->hasil_kunjungan : null;

        $arrears = $service->buildQuery($periodId, $petugasId, $status, $hasilKunjungan);

        $audit->log('admin.export', [
            'period_id' => $periodId,
            'petugas_id' => $petugasId,
            'status' => $status,
            'hasil_kunjungan' => $hasilKunjungan,
            'rows' => $arrears->count(),
        ], $request->user()->id);

        $fileName = 'hasil_penagihan_' . now()->format('Ymd_His') . '.xlsx';

        return $service->download($arrears, $fileName);
    }
}
