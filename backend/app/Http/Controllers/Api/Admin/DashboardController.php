<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodResource;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $service)
    {
        $data = $service->admin($request->integer('period_id') ?: null);

        return response()->json([
            'period' => $data['period'] ? new PeriodResource($data['period']) : null,
            'total' => $data['total'],
            'visited' => $data['visited'],
            'unvisited' => $data['unvisited'],
            'progress' => $data['progress'],
            'results' => $data['results'],
            'petugas_progress' => $data['petugas_progress'],
        ]);
    }

    public function petugasReview(Request $request, User $petugas, DashboardService $service)
    {
        $data = $service->petugasReview($petugas, $request->integer('period_id') ?: null);

        return response()->json($data);
    }
}
