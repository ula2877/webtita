<?php

namespace App\Http\Controllers\Api\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArrearResource;
use App\Http\Resources\PeriodResource;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $service)
    {
        $data = $service->petugas($request->user(), $request->integer('period_id') ?: null);

        return response()->json([
            'period' => $data['period'] ? new PeriodResource($data['period']) : null,
            'total' => $data['total'],
            'visited' => $data['visited'],
            'unvisited' => $data['unvisited'],
            'progress' => $data['progress'],
            'recent_arrears' => ArrearResource::collection($data['recent_arrears']->load(['customer', 'period', 'visit'])),
            'wilayah_stats' => $data['wilayah_stats'],
            'total_pelanggan' => $data['total_pelanggan'],
            'total_nominal_tagihan' => $data['total_nominal_tagihan'],
        ]);
    }
}
