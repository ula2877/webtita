<?php

namespace App\Http\Controllers\Api\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Period;
use Illuminate\Http\Request;

class PelangganController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->filled('period_id')
            ? Period::findOrFail($request->integer('period_id'))
            : Period::latest('year')->latest('month')->first();

        $query = Customer::query()
            ->whereHas('wilayah', fn ($q) => $q->where('petugas_id', $request->user()->id))
            ->with(['wilayah', 'tagihan' => fn ($q) => $q->when($period, fn ($p) => $p->where('period_id', $period->id))]);

        if ($request->filled('wilayah_id')) {
            $query->where('wilayah_id', $request->integer('wilayah_id'));
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('no_sambungan', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        $perPage = in_array($request->integer('per_page', 50), [25, 50, 100])
            ? $request->integer('per_page', 50)
            : 50;

        return CustomerResource::collection($query->orderBy('no_sambungan')->paginate($perPage)->withQueryString());
    }
}