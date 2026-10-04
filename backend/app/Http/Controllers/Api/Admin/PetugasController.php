<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePetugasRequest;
use App\Http\Requests\UpdatePetugasRequest;
use App\Http\Resources\PetugasResource;
use App\Models\Arrear;
use App\Models\Period;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PetugasController extends Controller
{
    public function index(Request $request)
    {
        // Default ke periode terbaru yang tersedia, bukan total lintas periode.
        $periodId = $request->filled('period_id')
            ? $request->integer('period_id')
            : Period::latest('year')->latest('month')->value('id');

        $petugas = User::where('role', User::ROLE_PETUGAS)
            ->orderBy('name')
            ->get()
            ->map(function ($user) use ($periodId) {
                $query = Arrear::where('petugas_id', $user->id);
                if ($periodId) {
                    $query->where('period_id', $periodId);
                }

                $total = $query->count();
                $visited = (clone $query)->where('status', Arrear::STATUS_SUDAH_DIKUNJUNGI)->count();

                $user->total_arrears = $total;
                $user->visited_arrears = $visited;
                $user->progress = $total > 0 ? round(($visited / $total) * 100, 1) : 0;

                return $user;
            });

        return PetugasResource::collection($petugas);
    }

    public function store(StorePetugasRequest $request, AuditService $audit)
    {
        $petugas = User::create([
            'name' => $request->validated('name'),
            'password' => bcrypt($request->validated('password')),
            'role' => User::ROLE_PETUGAS,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $audit->log('admin.create_petugas', ['petugas_id' => $petugas->id, 'name' => $petugas->name], $request->user()->id);

        return (new PetugasResource($petugas))
            ->additional(['message' => 'Petugas berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePetugasRequest $request, User $petugas, AuditService $audit)
    {
        $data = [
            'name' => $request->validated('name'),
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $petugas->update($data);

        $audit->log('admin.update_petugas', ['petugas_id' => $petugas->id], $request->user()->id);

        return (new PetugasResource($petugas))->additional(['message' => 'Petugas berhasil diperbarui.']);
    }

    public function toggleStatus(Request $request, User $petugas, AuditService $audit)
    {
        $petugas->update(['is_active' => ! $petugas->is_active]);

        $audit->log('admin.toggle_petugas', ['petugas_id' => $petugas->id, 'is_active' => $petugas->is_active], $request->user()->id);

        return (new PetugasResource($petugas))->additional([
            'message' => $petugas->is_active ? 'Petugas berhasil diaktifkan.' : 'Petugas berhasil dinonaktifkan.',
        ]);
    }
}
