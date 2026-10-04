<?php

namespace App\Http\Controllers\Api\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Resources\WilayahResource;
use App\Models\Wilayah;
use Illuminate\Http\Request;

class WilayahController extends Controller
{
    public function index(Request $request)
    {
        $wilayah = Wilayah::where('petugas_id', $request->user()->id)
            ->with('petugas')
            ->orderBy('code')
            ->get();

        return WilayahResource::collection($wilayah);
    }
}