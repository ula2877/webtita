<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function show(Request $request, string $path)
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (str_contains($path, '..') || str_contains($path, '/') || str_contains($path, '\\')) {
            return response()->json(['message' => 'Path tidak valid.'], 400);
        }

        $fullPath = 'photos/' . $path;

        if (! Storage::disk('public')->exists($fullPath)) {
            return response()->json(['message' => 'Foto tidak ditemukan.'], 404);
        }

        if ($request->user()->isPetugas()) {
            $ownsPhoto = Visit::where('foto_bukti', $fullPath)
                ->where('petugas_id', $request->user()->id)
                ->exists();

            if (! $ownsPhoto) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        return Storage::disk('public')->response($fullPath);
    }
}
