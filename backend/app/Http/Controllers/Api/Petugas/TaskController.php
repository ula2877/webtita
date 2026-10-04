<?php

namespace App\Http\Controllers\Api\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\VisitRequest;
use App\Http\Resources\ArrearResource;
use App\Http\Resources\VisitResource;
use App\Models\Arrear;
use App\Models\Period;
use App\Services\VisitService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Default ke periode terbaru yang tersedia.
        $periodId = $request->filled('period_id')
            ? $request->integer('period_id')
            : Period::latest('year')->latest('month')->value('id');

        $query = Arrear::where('petugas_id', $user->id)
            ->with(['customer', 'period', 'visit'])
            ->when($periodId, fn ($q) => $q->where('period_id', $periodId))
            ->orderByRaw("CASE WHEN status = 'belum_dikunjungi' THEN 0 ELSE 1 END")
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('no_sambungan', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        if ($request->filled('wilayah_id')) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('wilayah_id', $request->integer('wilayah_id'));
            });
        }

        $perPage = in_array($request->integer('per_page', 25), [25, 50, 100]) ? $request->integer('per_page', 25) : 25;

        return ArrearResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Request $request, Arrear $arrear)
    {
        $this->authorize('collect', $arrear);

        $arrear->load(['customer', 'period', 'petugas', 'visit.petugas']);

        return new ArrearResource($arrear);
    }

    public function visit(VisitRequest $request, Arrear $arrear, VisitService $service)
    {
        $this->authorize('collect', $arrear);

        $visit = $service->submit($arrear, $request->user(), $request->validated());

        return (new VisitResource($visit->load('petugas')))
            ->additional(['message' => 'Hasil kunjungan berhasil disimpan.'])
            ->response()
            ->setStatusCode(200);
    }
}
