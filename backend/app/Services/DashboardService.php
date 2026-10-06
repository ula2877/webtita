<?php

namespace App\Services;

use App\Models\Arrear;
use App\Models\Period;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function admin(?int $periodId): array
    {
        $period = $periodId ? Period::findOrFail($periodId) : Period::latest('year')->latest('month')->first();

        $query = Arrear::query()->with('visit', 'petugas');
        if ($period) {
            $query->where('period_id', $period->id);
        }

        $arrears = $query->get();

        $total = $arrears->count();
        $visited = $arrears->where('status', Arrear::STATUS_SUDAH_DIKUNJUNGI)->count();
        $unvisited = $total - $visited;

        $results = [
            'ada_orang' => 0,
            'rumah_kosong' => 0,
            'tidak_ada_orang' => 0,
            'lainnya' => 0,
        ];

        foreach ($arrears as $arrear) {
            if ($arrear->visit) {
                $key = $arrear->visit->status_kunjungan;
                if (isset($results[$key])) {
                    $results[$key]++;
                }
            }
        }

        $petugasProgress = $arrears
            ->filter(fn ($a) => $a->petugas_id !== null)
            ->groupBy('petugas_id')
            ->map(function ($items, $petugasId) {
                $petugas = $items->first()->petugas;

                return [
                    'petugas' => $petugas ? ['id' => $petugas->id, 'name' => $petugas->name] : ['id' => (int) $petugasId, 'name' => 'Unknown'],
                    'total' => $items->count(),
                    'visited' => $items->where('status', Arrear::STATUS_SUDAH_DIKUNJUNGI)->count(),
                    'progress' => $items->count() > 0
                        ? round(($items->where('status', Arrear::STATUS_SUDAH_DIKUNJUNGI)->count() / $items->count()) * 100, 1)
                        : 0,
                ];
            })
            ->values();

        return [
            'period' => $period,
            'total' => $total,
            'visited' => $visited,
            'unvisited' => $unvisited,
            'progress' => $total > 0 ? round(($visited / $total) * 100, 1) : 0,
            'results' => $results,
            'petugas_progress' => $petugasProgress,
        ];
    }

    public function petugas(User $petugas, ?int $periodId): array
    {
        $period = $periodId ? Period::findOrFail($periodId) : Period::latest('year')->latest('month')->first();

        $query = Arrear::where('petugas_id', $petugas->id)->with('visit');
        if ($period) {
            $query->where('period_id', $period->id);
        }

        $arrears = $query->get();

        $total = $arrears->count();
        $visited = $arrears->where('status', Arrear::STATUS_SUDAH_DIKUNJUNGI)->count();
        $unvisited = $total - $visited;

        $recent = $arrears
            ->sortByDesc('updated_at')
            ->take(5)
            ->values();

        $wilayahStats = [];
        $totalPelanggan = 0;
        $totalNominalTagihan = 0;

        $wilayah = $petugas->wilayah()->withCount('pelanggan')->get(['id', 'code', 'name']);
        if ($wilayah->isNotEmpty()) {
            $wilayahIds = $wilayah->pluck('id');
            $totalsByWilayah = $period
                ? Arrear::query()
                    ->where('period_id', $period->id)
                    ->whereHas('customer', fn ($q) => $q->whereIn('wilayah_id', $wilayahIds))
                    ->with('customer:id,wilayah_id')
                    ->get()
                    ->groupBy(fn ($a) => $a->customer->wilayah_id)
                    ->map(fn ($items) => $items->sum('jumlah_tagihan'))
                : collect();

            foreach ($wilayah as $w) {
                $customerCount = $w->pelanggan_count ?? 0;
                $totalPelanggan += $customerCount;

                $wilayahStats[] = [
                    'id' => $w->id,
                    'code' => $w->code,
                    'name' => $w->name,
                    'customer_count' => $customerCount,
                    'total_tagihan' => $totalsByWilayah[$w->id] ?? 0,
                ];
            }

            $totalNominalTagihan = $totalsByWilayah->sum() ?? 0;
        }

        return [
            'period' => $period,
            'total' => $total,
            'visited' => $visited,
            'unvisited' => $unvisited,
            'progress' => $total > 0 ? round(($visited / $total) * 100, 1) : 0,
            'recent_arrears' => $recent,
            'wilayah_stats' => $wilayahStats,
            'total_pelanggan' => $totalPelanggan,
            'total_nominal_tagihan' => $totalNominalTagihan,
        ];
    }

    public function petugasReview(User $petugas, ?int $periodId): array
    {
        $period = $periodId ? Period::findOrFail($periodId) : Period::latest('year')->latest('month')->first();

        $query = Arrear::where('petugas_id', $petugas->id);
        if ($period) {
            $query->where('period_id', $period->id);
        }

        $arrears = $query->with(['visit', 'customer.wilayah'])->get();

        $totalTagihan = $arrears->count();
        $denganFoto = $arrears->whereNotNull('foto_bukti')->where('foto_bukti', '!=', '')->count();

        $results = [
            'ada_orang' => 0,
            'rumah_kosong' => 0,
            'tidak_ada_orang' => 0,
            'lainnya' => 0,
            'belum_dikunjungi' => 0,
        ];

        foreach ($arrears as $arrear) {
            if ($arrear->visit) {
                $key = $arrear->visit->status_kunjungan;
                if (isset($results[$key])) {
                    $results[$key]++;
                }
            } else {
                $results['belum_dikunjungi']++;
            }
        }

        // Group by wilayah (region) for the table
        $byArea = $arrears->groupBy(fn ($arrear) => $arrear->customer->wilayah->id ?? $arrear->customer->wilayah->name ?? 'unknown')
            ->map(function ($items) {
                $firstItem = $items->first();
                $wilayah = $firstItem->customer->wilayah ?? null;
                $wilayahKode = $wilayah->code ?? '';
                $wilayahNama = $wilayah->name ?? 'Wilayah Tidak Diketahui';
                $totalSurat = $items->count();
                $buktiFoto = $items->whereNotNull('foto_bukti')->where('foto_bukti', '!=', '')->count();
                $sudahDikunjungi = $items->where('status', Arrear::STATUS_SUDAH_DIKUNJUNGI)->count();
                $suratDiterima = $items->where('visit.status_kunjungan', Visit::HASIL_ADA_ORANG)->count();
                $tidakAdaOrang = $items->where('visit.status_kunjungan', Visit::HASIL_TIDAK_ADA_ORANG)->count();
                $rumahKosong = $items->where('visit.status_kunjungan', Visit::HASIL_RUMAH_KOSONG)->count();
                $lainnya = $items->where('visit.status_kunjungan', Visit::HASIL_LAINNYA)->count();
                $belumDikunjungi = $items->where('status', Arrear::STATUS_BELUM_DIKUNJUNGI)->count();
                $sisaSurat = $totalSurat - $sudahDikunjungi;
                $totalNominal = $items->sum('jumlah_tagihan');

                return [
                    'wilayah_id' => $wilayah->id ?? null,
                    'kode_wilayah' => $wilayahKode,
                    'wilayah_nama' => $wilayahNama,
                    'total_nominal' => $totalNominal,
                    'jumlah_surat' => $totalSurat,
                    'bukti_foto' => $buktiFoto,
                    'surat_diterima' => $suratDiterima,
                    'tidak_ada_orang' => $tidakAdaOrang,
                    'rumah_kosong' => $rumahKosong,
                    'lainnya' => $lainnya,
                    'sisa_surat' => $sisaSurat,
                ];
            })
            ->values()
            ->sortByDesc('jumlah_surat')
            ->values();

        return [
            'petugas' => [
                'id' => $petugas->id,
                'name' => $petugas->name,
            ],
            'period' => $period ? ['id' => $period->id, 'label' => $period->label] : null,
            'kpi' => [
                'total_tagihan' => $totalTagihan,
                'dengan_foto' => $denganFoto,
                'ada_orang' => $results['ada_orang'],
                'tidak_ada_orang' => $results['tidak_ada_orang'],
                'rumah_kosong' => $results['rumah_kosong'],
                'lainnya' => $results['lainnya'],
                'belum_dikunjungi' => $results['belum_dikunjungi'],
            ],
            'by_area' => $byArea,
        ];
    }
}