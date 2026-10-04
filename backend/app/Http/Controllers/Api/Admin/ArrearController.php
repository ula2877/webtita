<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRequest;
use App\Http\Requests\BulkDeleteArrearRequest;
use App\Http\Requests\UpdateArrearRequest;
use App\Http\Resources\ArrearResource;
use App\Models\Arrear;
use App\Models\Customer;
use App\Models\Wilayah;
use App\Services\AssignmentService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArrearController extends Controller
{
    public function index(Request $request)
    {
        $query = Arrear::query()
            ->with(['customer.wilayah', 'period', 'petugas', 'visit.petugas']);

        if ($request->filled('period_id')) {
            $query->where('period_id', $request->integer('period_id'));
        }

        if ($request->filled('petugas_id')) {
            $query->where('petugas_id', $request->integer('petugas_id'));
        }

        if ($request->filled('wilayah_id')) {
            $wilayahId = $request->integer('wilayah_id');
            $query->whereHas('customer', fn ($q) => $q->where('wilayah_id', $wilayahId));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('hasil_kunjungan')) {
            $query->whereHas('visit', fn ($q) => $q->where('status_kunjungan', $request->hasil_kunjungan));
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('no_sambungan', 'like', "%{$search}%")
                        ->orWhere('nama', 'like', "%{$search}%");
                });
            });
        }

        $this->applySort($query, $request->get('sort'), $request->get('order', 'asc'));

        $perPage = in_array($request->integer('per_page', 25), [25, 50, 100]) ? $request->integer('per_page', 25) : 25;

        return ArrearResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(Arrear $arrear)
    {
        $arrear->load(['customer.wilayah', 'period', 'petugas', 'visit.petugas']);

        return new ArrearResource($arrear);
    }

    public function update(UpdateArrearRequest $request, Arrear $arrear, AuditService $audit)
    {
        $arrear->update($request->validated());

        $audit->log('admin.update_arrear', ['arrear_id' => $arrear->id], $request->user()->id);

        $arrear->load(['customer.wilayah', 'period', 'petugas', 'visit.petugas']);

        return (new ArrearResource($arrear))->additional(['message' => 'Data berhasil diperbarui.']);
    }

    public function destroy(Request $request, Arrear $arrear, AuditService $audit)
    {
        $arrear->delete();

        $audit->log('admin.delete_arrear', ['arrear_id' => $arrear->id], $request->user()->id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    public function destroyBulk(BulkDeleteArrearRequest $request, AuditService $audit)
    {
        $ids = $request->input('ids');

        $deleted = DB::transaction(function () use ($ids) {
            return Arrear::whereIn('id', $ids)->delete();
        });

        $audit->log('admin.bulk_delete_arrears', [
            'arrear_ids' => $ids,
            'count' => $deleted,
        ], $request->user()->id);

        return response()->json([
            'message' => "{$deleted} data tunggakan berhasil dihapus.",
            'deleted_count' => $deleted,
        ]);
    }

    public function assign(AssignRequest $request, AssignmentService $service)
    {
        $count = $service->assign(
            $request->input('arrear_ids'),
            (int) $request->input('petugas_id'),
            $request->user()->id
        );

        return response()->json([
            'message' => "{$count} data tunggakan berhasil ditugaskan.",
            'count' => $count,
        ]);
    }

    private function applySort($query, ?string $sort, string $order)
    {
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';

        $columns = ['id', 'status', 'jumlah_bulan_tunggakan', 'petugas_id', 'created_at'];
        if (in_array($sort, $columns, true)) {
            $query->orderBy($sort, $order);

            return;
        }

        if ($sort === 'no_sambungan') {
            $query->orderBy(
                Customer::select('no_sambungan')->whereColumn('customers.id', 'arrears.customer_id'),
                $order
            );

            return;
        }

        if ($sort === 'nama') {
            $query->orderBy(
                Customer::select('nama')->whereColumn('customers.id', 'arrears.customer_id'),
                $order
            );

            return;
        }

        if ($sort === 'wilayah') {
            $query->orderBy(
                Wilayah::select('name')->whereColumn('wilayah.id', 'customers.wilayah_id')->whereColumn('customers.id', 'arrears.customer_id'),
                $order
            );

            return;
        }
    }
}
