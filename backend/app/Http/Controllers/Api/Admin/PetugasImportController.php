<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PetugasImportRequest;
use App\Services\AuditService;
use App\Services\KetuaKelompokImportService;
use Illuminate\Support\Facades\Storage;

class PetugasImportController extends Controller
{
    public function store(PetugasImportRequest $request, KetuaKelompokImportService $service, AuditService $audit)
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

            $audit->log('admin.sync_petugas', [
                'file_name' => $originalName,
                'sheet_name' => $result['sheet_name'],
                'total' => $result['total'],
                'created' => $result['created'],
                'existing' => $result['existing'],
                'invalid' => $result['invalid'],
            ], $request->user()->id);

            return response()->json($result, 201);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }
}