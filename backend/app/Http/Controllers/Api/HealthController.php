<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check()
    {
        $status = 'ok';

        try {
            DB::select('select 1');
            $database = 'ok';
        } catch (\Throwable $e) {
            $status = 'degraded';
            $database = 'error';
        }

        return response()->json([
            'status' => $status,
            'database' => $database,
            'timestamp' => now()->toIso8601String(),
        ], $status === 'ok' ? 200 : 503);
    }
}