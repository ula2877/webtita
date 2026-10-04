<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PeriodResource;
use App\Models\Period;

class PeriodController extends Controller
{
    public function index()
    {
        Period::ensureThroughCurrentMonth();

        $now = now();

        $periods = Period::query()
            ->where('year', '<', $now->year)
            ->orWhere(function ($query) use ($now) {
                $query->where('year', $now->year)->where('month', '<=', $now->month);
            })
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        return PeriodResource::collection($periods);
    }
}