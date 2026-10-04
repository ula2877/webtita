<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PetugasImportRequest;
use App\Services\AuditService;
use App\Services\StrukturExcelImportService;
use Illuminate\Support\Facades\Storage;

class WilayahImportController extends Controller
{
    public function store(PetugasImportRequest $request, StrukturExcelImportService $service, AuditService $audit)
    {
        $file = $request->file('file');
        $storedPath = $file->store('tmp_imports');
        $originalName = $file->getClientOriginalName();

        try {
            $result = $service->import(
                Storage::disk('local')->path($storedPath),
                $request->user()->id,
                $originalName
            );

            $audit->log('admin.sync_wilayah', [
                'file_name' => $originalName,
                'petugas_created' => $result['petugas']['created'],
                'petugas_existing' => $result['petugas']['existing'],
                'wilayah_created' => $result['wilayah']['created'],
                'wilayah_updated' => $result['wilayah']['updated'],
                'wilayah_invalid' => $result['wilayah']['invalid'],
                'pelanggan_created' => $result['detail_tagihan']['pelanggan_created'],
                'pelanggan_updated' => $result['detail_tagihan']['pelanggan_updated'],
                'tagihan_created' => $result['detail_tagihan']['tagihan_created'],
                'tagihan_updated' => $result['detail_tagihan']['tagihan_updated'],
                'detail_invalid' => $result['detail_tagihan']['invalid'],
            ], $request->user()->id);

            return response()->json($result, 201);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }
}