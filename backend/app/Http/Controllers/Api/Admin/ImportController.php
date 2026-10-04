<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRequest;
use App\Http\Resources\ImportLogResource;
use App\Models\Arrear;
use App\Models\ImportLog;
use App\Services\AuditService;
use App\Services\ExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function store(ImportRequest $request, ExcelImportService $service, AuditService $audit)
    {
        $periodId = (int) $request->input('period_id');
        $replace = $request->boolean('replace');

        $periodHasData = Arrear::where('period_id', $periodId)->exists();

        if ($periodHasData && ! $replace) {
            return response()->json([
                'message' => 'Data untuk periode ini sudah pernah diimport.',
                'code' => 'period_already_imported',
                'replace_required' => true,
            ], 409);
        }

        $file = $request->file('file');
        $storedPath = $file->store('tmp_imports');

        try {
            $importLog = $service->import(
                Storage::disk('local')->path($storedPath),
                $periodId,
                $request->user()->id,
                $replace
            );

            $audit->log('admin.import', [
                'import_log_id' => $importLog->id,
                'period_id' => $periodId,
                'file_name' => $file->getClientOriginalName(),
                'replace' => $replace,
                'success' => $importLog->success_rows,
                'failed' => $importLog->failed_rows,
            ], $request->user()->id);

            return (new ImportLogResource($importLog->load(['period', 'uploader'])))
                ->additional(['message' => 'Import selesai.'])
                ->response()
                ->setStatusCode(201);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    public function index(Request $request)
    {
        $query = ImportLog::query()->with(['period', 'uploader']);

        if ($request->filled('period_id')) {
            $query->where('period_id', $request->integer('period_id'));
        }

        $perPage = in_array($request->integer('per_page', 25), [25, 50, 100]) ? $request->integer('per_page', 25) : 25;

        return ImportLogResource::collection($query->orderByDesc('id')->paginate($perPage)->withQueryString());
    }

    public function show(ImportLog $importLog)
    {
        $importLog->load(['period', 'uploader']);

        return new ImportLogResource($importLog);
    }
}
