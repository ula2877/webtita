<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WilayahResource;
use App\Models\Wilayah;
use Illuminate\Http\Request;

class WilayahController extends Controller
{
    public function index(Request $request)
    {
        $query = Wilayah::query()->with('petugas');

        if ($request->filled('search')) {
            $search = trim($request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('petugas_id')) {
            $query->where('petugas_id', $request->integer('petugas_id'));
        }

        $perPage = in_array($request->integer('per_page', 25), [25, 50, 100])
            ? $request->integer('per_page', 25)
            : 25;

        return WilayahResource::collection($query->orderBy('code')->paginate($perPage)->withQueryString());
    }
}