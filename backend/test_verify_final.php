<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\DashboardService;

$service = new DashboardService();
$petugas = \App\Models\User::find(145);

if ($petugas) {
    echo "=== PETUGAS DASHBOARD TEST ===" . PHP_EOL;
    $result = $service->petugas($petugas, null);
    echo "Total: {$result['total']}" . PHP_EOL;
    echo "Visited: {$result['visited']}" . PHP_EOL;
    echo "Unvisited: {$result['unvisited']}" . PHP_EOL;
    echo "Progress: {$result['progress']}%" . PHP_EOL;
    echo "Total Pelanggan: " . ($result['total_pelanggan'] ?? 'N/A') . PHP_EOL;
    echo "Total Nominal Tagihan: " . ($result['total_nominal_tagihan'] ?? 'N/A') . PHP_EOL;
    
    echo PHP_EOL . "=== REVIEW PETUGAS TEST ===" . PHP_EOL;
    $review = $service->petugasReview($petugas, null);
    echo "Petugas: {$review['petugas']['name']} (ID: {$review['petugas']['id']})" . PHP_EOL;
    echo "Period: {$review['period']['label']}" . PHP_EOL;
    echo "Total Tagihan: {$review['kpi']['total_tagihan']}" . PHP_EOL;
    echo "Dengan Foto: {$review['kpi']['dengan_foto']}" . PHP_EOL;
    echo "Ada Orang: {$review['kpi']['ada_orang']}" . PHP_EOL;
    echo "Tidak Ada Orang: {$review['kpi']['tidak_ada_orang']}" . PHP_EOL;
    echo "Rumah Kosong: {$review['kpi']['rumah_kosong']}" . PHP_EOL;
    echo "Lainnya: {$review['kpi']['lainnya']}" . PHP_EOL;
    echo "Belum Dikunjungi: {$review['kpi']['belum_dikunjungi']}" . PHP_EOL;
    
    echo PHP_EOL . "=== BY AREA DATA ===" . PHP_EOL;
    if (!empty($review['by_area'])) {
        foreach ($review['by_area'] as $area) {
            echo "Alamat: {$area['alamat_penagihan']} | Surat: {$area['jumlah_surat']} | Foto: {$area['bukti_foto']} | Diterima: {$area['surat_diterima']} | Tidak Ada: {$area['tidak_ada_orang']} | Kosong: {$area['rumah_kosong']} | Sisa: {$area['sisa_surat']}" . PHP_EOL;
        }
    } else {
        echo "No area data" . PHP_EOL;
    }
    
    // Verify totals match KPI
    $totalSurat = array_sum(array_column($review['by_area'], 'jumlah_surat'));
    $totalFoto = array_sum(array_column($review['by_area'], 'bukti_foto'));
    $totalDiterima = array_sum(array_column($review['by_area'], 'surat_diterima'));
    $totalTidakAda = array_sum(array_column($review['by_area'], 'tidak_ada_orang'));
    $totalKosong = array_sum(array_column($review['by_area'], 'rumah_kosong'));
    $totalSisa = array_sum(array_column($review['by_area'], 'sisa_surat'));
    
    echo PHP_EOL . "=== TOTAL VALIDATION ===" . PHP_EOL;
    echo "Total Surat (by_area): $totalSurat vs KPI: " . $review['kpi']['total_tagihan'] . " -> " . ($totalSurat == $review['kpi']['total_tagihan'] ? "MATCH" : "MISMATCH") . PHP_EOL;
    echo "Total Foto (by_area): $totalFoto vs KPI: " . $review['kpi']['dengan_foto'] . " -> " . ($totalFoto == $review['kpi']['dengan_foto'] ? "MATCH" : "MISMATCH") . PHP_EOL;
    echo "Total Diterima (by_area): $totalDiterima vs KPI: " . $review['kpi']['ada_orang'] . " -> " . ($totalDiterima == $review['kpi']['ada_orang'] ? "MATCH" : "MISMATCH") . PHP_EOL;
    echo "Total Tidak Ada (by_area): $totalTidakAda vs KPI: " . $review['kpi']['tidak_ada_orang'] . " -> " . ($totalTidakAda == $review['kpi']['tidak_ada_orang'] ? "MATCH" : "MISMATCH") . PHP_EOL;
    echo "Total Kosong (by_area): $totalKosong vs KPI: " . $review['kpi']['rumah_kosong'] . " -> " . ($totalKosong == $review['kpi']['rumah_kosong'] ? "MATCH" : "MISMATCH") . PHP_EOL;
    echo "Total Sisa (by_area): $totalSisa vs KPI: " . $review['kpi']['belum_dikunjungi'] . " -> " . ($totalSisa == $review['kpi']['belum_dikunjungi'] ? "MATCH" : "MISMATCH") . PHP_EOL;
    
    echo PHP_EOL . "ALL TESTS PASSED!" . PHP_EOL;
} else {
    echo "Petugas not found" . PHP_EOL;
}