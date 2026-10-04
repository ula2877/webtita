<?php

namespace App\Services;

use App\Models\Arrear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function assign(array $arrearIds, int $petugasId, int $assignedBy): int
    {
        $petugas = User::where('role', User::ROLE_PETUGAS)->find($petugasId);

        if (! $petugas) {
            throw ValidationException::withMessages([
                'petugas_id' => ['Petugas tidak ditemukan.'],
            ]);
        }

        $count = DB::transaction(function () use ($arrearIds, $petugasId) {
            return Arrear::whereIn('id', $arrearIds)->update(['petugas_id' => $petugasId]);
        });

        app(AuditService::class)->log('admin.assign', [
            'arrear_ids' => $arrearIds,
            'petugas_id' => $petugasId,
            'count' => $count,
        ], $assignedBy);

        return $count;
    }
}
