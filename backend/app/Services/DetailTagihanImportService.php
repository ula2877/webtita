<?php

namespace App\Services;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\ImportLog;
use App\Models\Period;
use App\Models\Wilayah;
use App\Models\WilayahAlias;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DetailTagihanImportService
{
    private const SHEET_NAME = 'detail tagihan';

    private const HEADER_ALIASES = [
        'no' => 'no',
        'nomor' => 'no',
        'nosambungan' => 'no_sambungan',
        'nama' => 'nama',
        'alamat' => 'alamat',
        'bln' => 'bln',
        'bulan' => 'bln',
        'tagihan' => 'tagihan',
        'jumlah' => 'tagihan',
    ];

    /** @var array key nama wilayah ternormalisasi (case/spasi) => Wilayah */
    private array $wilayahByName = [];

    /** @var array key nama wilayah ternormalisasi (tanpa tanda baca) => Wilayah */
    private array $wilayahByNormalized = [];

    /** @var array key alias ternormalisasi => Wilayah */
    private array $wilayahByAlias = [];

    public function import(string $filePath, int $uploadedBy, ?string $originalFileName = null): array
    {
        $spreadsheet = IOFactory::load($filePath);

        $sheets = $this->findSheets($spreadsheet);
        if (count($sheets) === 0) {
            throw ValidationException::withMessages([
                'file' => ['Sheet "detail tagihan" tidak ditemukan pada file Excel.'],
            ]);
        }

        $this->loadWilayahLookup();

        $seenPerPeriod = [];
        $periodKeys = [];
        $wilayahNotFound = [];

        $total = 0;
        $invalid = 0;
        $pelangganCreated = 0;
        $pelangganUpdated = 0;
        $tagihanCreated = 0;
        $tagihanUpdated = 0;
        $sheetSummaries = [];
        $allDetails = [];

        foreach ($sheets as $sheet) {
            $result = $this->importSheet(
                $sheet,
                $filePath,
                $uploadedBy,
                $originalFileName,
                $seenPerPeriod,
                $periodKeys,
                $wilayahNotFound
            );

            $total += $result['total'];
            $invalid += $result['invalid'];
            $pelangganCreated += $result['pelanggan_created'];
            $pelangganUpdated += $result['pelanggan_updated'];
            $tagihanCreated += $result['tagihan_created'];
            $tagihanUpdated += $result['tagihan_updated'];
            $sheetSummaries[] = $result['summary'];
            $allDetails = array_merge($allDetails, $result['details']);
        }

        $periodLabels = array_map(fn (array $s) => $s['period'], $sheetSummaries);
        $periodLabel = count(array_unique(array_filter($periodLabels))) === 1 ? reset($periodLabels) : null;

        return [
            'message' => 'Sinkronisasi pelanggan & tagihan selesai.',
            'sheet_count' => count($sheets),
            'sheet_name' => count($sheets) === 1 ? $sheets[0]->getTitle() : 'detail tagihan',
            'period_label' => $periodLabel,
            'sheets' => $sheetSummaries,
            'total' => $total,
            'pelanggan_created' => $pelangganCreated,
            'pelanggan_updated' => $pelangganUpdated,
            'tagihan_created' => $tagihanCreated,
            'tagihan_updated' => $tagihanUpdated,
            'invalid' => $invalid,
            'wilayah_not_found' => array_values($wilayahNotFound),
            'details' => $allDetails,
        ];
    }

    private function importSheet(
        Worksheet $sheet,
        string $filePath,
        int $uploadedBy,
        ?string $originalFileName,
        array &$seenPerPeriod,
        array &$periodKeys,
        array &$wilayahNotFound
    ): array {
        $plan = [];
        $total = 0;
        $invalid = 0;

        $currentWilayahName = null;
        $currentWilayah = null;
        $currentDate = null;
        $columnMap = null;

        foreach ($sheet->getRowIterator() as $row) {
            $rowNumber = $row->getRowIndex();
            $cells = $this->readCells($row);

            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $wilayahHeader = $this->detectWilayahHeader($cells);
            if ($wilayahHeader) {
                $currentWilayahName = $this->normalizeName($wilayahHeader['name']);
                $currentDate = $wilayahHeader['date'];
                $currentWilayah = $this->matchWilayah($currentWilayahName);
                $columnMap = null;

                if (! $currentWilayah && ! isset($wilayahNotFound[$this->normalizeNameKey($currentWilayahName)])) {
                    $wilayahNotFound[$this->normalizeNameKey($currentWilayahName)] = $currentWilayahName;
                }

                continue;
            }

            $headerMap = $this->detectCustomerHeader($cells);
            if ($headerMap) {
                $columnMap = $headerMap;

                continue;
            }

            if (! $columnMap) {
                continue;
            }

            $noSambungan = $this->cellText($cells[$columnMap['no_sambungan']] ?? null);
            if ($noSambungan === '') {
                continue;
            }

            $total++;

            $entry = $this->parseCustomerRow(
                $rowNumber,
                $cells,
                $columnMap,
                $currentWilayahName,
                $currentWilayah,
                $currentDate
            );

            if ($entry['status'] === 'invalid') {
                $invalid++;
                $plan[] = $entry;
                continue;
            }

            if ($entry['wilayah_id'] === null) {
                $invalid++;
                $entry['status'] = 'invalid';
                $entry['note'] = 'Wilayah tidak ditemukan, pelanggan tidak diimport.';
                $plan[] = $entry;
                continue;
            }

            $periodKey = $entry['period']; // 'Y-n'
            if (isset($seenPerPeriod[$periodKey][$entry['no_sambungan']])) {
                $invalid++;
                $entry['status'] = 'invalid';
                $entry['note'] = "No. sambungan duplikat dalam file periode ini (baris {$seenPerPeriod[$periodKey][$entry['no_sambungan']]}), dilewati.";
                $plan[] = $entry;
                continue;
            }
            $seenPerPeriod[$periodKey][$entry['no_sambungan']] = $rowNumber;
            $periodKeys[$periodKey] = true;

            $plan[] = $entry;
        }

        $pelangganCreated = 0;
        $pelangganUpdated = 0;
        $tagihanCreated = 0;
        $tagihanUpdated = 0;
        $details = [];
        $resolvedPeriodId = null;

        DB::transaction(function () use (
            $plan,
            $uploadedBy,
            $originalFileName,
            $sheet,
            $filePath,
            $periodKeys,
            &$total,
            &$invalid,
            &$pelangganCreated,
            &$pelangganUpdated,
            &$tagihanCreated,
            &$tagihanUpdated,
            &$resolvedPeriodId,
            &$details
        ) {
            $periodIds = $this->resolvePeriods($periodKeys);
            $singlePeriodId = count($periodIds) === 1 ? reset($periodIds) : null;
            $resolvedPeriodId = $singlePeriodId;

            foreach ($plan as $entry) {
                if ($entry['status'] !== 'upsert') {
                    $details[] = [
                        'row' => $entry['row'],
                        'no_sambungan' => $entry['no_sambungan'],
                        'nama' => $entry['nama'],
                        'wilayah' => $entry['wilayah_raw'] ?: '-',
                        'bulan' => $entry['bulan'],
                        'tagihan' => $entry['tagihan'],
                        'status' => 'invalid',
                        'note' => $entry['note'],
                    ];
                    continue;
                }

                $periodId = $periodIds[$entry['period']];

                $customer = Customer::updateOrCreate(
                    ['no_sambungan' => $entry['no_sambungan']],
                    [
                        'nama' => $entry['nama'],
                        'address' => $entry['address'],
                        'wilayah_id' => $entry['wilayah_id'],
                    ]
                );

                $arrear = Arrear::updateOrCreate(
                    ['customer_id' => $customer->id, 'period_id' => $periodId],
                    [
                        'jumlah_bulan_tunggakan' => $entry['bulan'],
                        'jumlah_tagihan' => $entry['tagihan'],
                        'petugas_id' => $entry['petugas_id'],
                    ]
                );

                if ($customer->wasRecentlyCreated) {
                    $pelangganCreated++;
                } else {
                    $pelangganUpdated++;
                }

                if ($arrear->wasRecentlyCreated) {
                    $tagihanCreated++;
                } else {
                    $tagihanUpdated++;
                }

                $details[] = [
                    'row' => $entry['row'],
                    'no_sambungan' => $entry['no_sambungan'],
                    'nama' => $entry['nama'],
                    'wilayah' => $entry['wilayah_raw'],
                    'bulan' => $entry['bulan'],
                    'tagihan' => $entry['tagihan'],
                    'status' => $arrear->wasRecentlyCreated ? 'created' : 'updated',
                    'note' => $arrear->wasRecentlyCreated
                        ? 'Pelanggan & tagihan baru dibuat.'
                        : 'Data tagihan diperbarui.',
                ];
            }

            $status = $invalid > 0 ? ImportLog::STATUS_PARTIAL : ImportLog::STATUS_SUCCESS;

            ImportLog::create([
                'period_id' => $singlePeriodId,
                'uploaded_by' => $uploadedBy,
                'file_name' => $originalFileName ?: basename($filePath),
                'sheet_name' => $sheet->getTitle(),
                'total_rows' => $total,
                'success_rows' => $pelangganCreated + $pelangganUpdated,
                'failed_rows' => $invalid,
                'status' => $status,
                'error_details' => array_map(fn (array $d) => [
                    'row' => $d['row'],
                    'type' => $d['status'] === 'invalid' ? 'error' : 'success',
                    'message' => $this->detailMessage($d),
                ], $details),
            ]);
        });

        return [
            'summary' => [
                'sheet_name' => $sheet->getTitle(),
                'period_id' => $resolvedPeriodId,
                'period' => $resolvedPeriodId ? Period::find($resolvedPeriodId)?->label : null,
                'total' => $total,
                'pelanggan_created' => $pelangganCreated,
                'pelanggan_updated' => $pelangganUpdated,
                'tagihan_created' => $tagihanCreated,
                'tagihan_updated' => $tagihanUpdated,
                'invalid' => $invalid,
            ],
            'total' => $total,
            'invalid' => $invalid,
            'pelanggan_created' => $pelangganCreated,
            'pelanggan_updated' => $pelangganUpdated,
            'tagihan_created' => $tagihanCreated,
            'tagihan_updated' => $tagihanUpdated,
            'details' => $details,
        ];
    }

    private function parseCustomerRow(
        int $rowNumber,
        array $cells,
        array $columnMap,
        ?string $wilayahName,
        ?Wilayah $wilayah,
        ?Carbon $date
    ): array {
        $base = [
            'row' => $rowNumber,
            'wilayah_raw' => $wilayahName,
            'wilayah_id' => $wilayah->id ?? null,
            'petugas_id' => $wilayah->petugas_id ?? null,
            'period' => $date ? $date->format('Y-n') : null,
        ];

        $noSambungan = $this->cellText($cells[$columnMap['no_sambungan']] ?? null);
        $nama = $this->normalizeName($this->cellText($cells[$columnMap['nama']] ?? null));
        $address = $this->normalizeName($this->cellText($cells[$columnMap['alamat']] ?? null));
        $bulan = $this->toInt($this->cellText($cells[$columnMap['bln']] ?? null));
        $tagihan = $this->toInt($this->cellText($cells[$columnMap['tagihan']] ?? null));

        $base['no_sambungan'] = $noSambungan;
        $base['nama'] = $nama;
        $base['address'] = $address;
        $base['bulan'] = $bulan;
        $base['tagihan'] = $tagihan;

        if ($noSambungan === '') {
            $base['status'] = 'invalid';
            $base['note'] = 'NO.SAMBUNGAN kosong.';

            return $base;
        }

        if ($nama === '') {
            $base['status'] = 'invalid';
            $base['note'] = 'NAMA kosong.';

            return $base;
        }

        if (! $date) {
            $base['status'] = 'invalid';
            $base['note'] = 'Periode kelompok tidak ditemukan.';

            return $base;
        }

        if ($bulan === null) {
            $base['status'] = 'invalid';
            $base['note'] = 'BLN tidak valid.';

            return $base;
        }

        if ($tagihan === null) {
            $base['status'] = 'invalid';
            $base['note'] = 'TAGIHAN tidak valid.';

            return $base;
        }

        $base['status'] = 'upsert';
        $base['note'] = '';

        return $base;
    }

    private function matchWilayah(string $name): ?Wilayah
    {
        $aliasService = new WilayahAliasService();

        // 1. Exact match (case & whitespace dinormalisasi, tanda baca tetap).
        if (isset($this->wilayahByName[$this->normalizeNameKey($name)])) {
            return $this->wilayahByName[$this->normalizeNameKey($name)];
        }

        // 2. Normalized exact match (koma/titik/spasi ganda diabaikan).
        if (isset($this->wilayahByNormalized[$aliasService->normalizeWilayahNameKey($name)])) {
            return $this->wilayahByNormalized[$aliasService->normalizeWilayahNameKey($name)];
        }

        // 3. Alias match (via tabel wilayah_aliases di database).
        return $this->wilayahByAlias[$aliasService->normalizeWilayahNameKey($name)] ?? null;
    }

    private function loadWilayahLookup(): void
    {
        $aliasService = new WilayahAliasService();

        $wilayah = Wilayah::query()->with('petugas')->get(['id', 'name', 'petugas_id']);
        foreach ($wilayah as $w) {
            $this->wilayahByName[$this->normalizeNameKey($w->name)] = $w;
            $this->wilayahByNormalized[$aliasService->normalizeWilayahNameKey($w->name)] = $w;
        }

        foreach (WilayahAlias::query()->with('wilayah')->get() as $alias) {
            $this->wilayahByAlias[$aliasService->normalizeWilayahNameKey($alias->alias)] = $alias->wilayah;
        }
    }

    private function resolvePeriods(array $periodKeys): array
    {
        $ids = [];
        foreach (array_keys($periodKeys) as $key) {
            [$year, $month] = explode('-', $key . '-');
            $period = Period::firstOrCreate(
                ['year' => (int) $year, 'month' => (int) $month],
                ['label' => Period::makeLabel((int) $year, (int) $month)]
            );
            $ids[$key] = $period->id;
        }

        return $ids;
    }

    private function findSheets($spreadsheet): array
    {
        $targetKey = $this->normalizeNameKey(self::SHEET_NAME);

        $sheets = [];

        foreach ($spreadsheet->getSheetNames() as $name) {
            if (str_starts_with($this->normalizeNameKey($name), $targetKey)) {
                $sheets[] = $spreadsheet->getSheetByName($name);
            }
        }

        usort($sheets, fn (Worksheet $a, Worksheet $b) => strcmp($a->getTitle(), $b->getTitle()));

        return $sheets;
    }

    private function readCells($row): array
    {
        $cells = [];
        foreach ($row->getCellIterator() as $cell) {
            $cells[$cell->getColumn()] = [
                'raw' => $cell->getValue(),
                'fmt' => trim((string) $cell->getFormattedValue()),
                'isDate' => Date::isDateTime($cell),
            ];
        }

        return $cells;
    }

    private function detectWilayahHeader(array $cells): ?array
    {
        $dateCol = null;
        $date = null;

        foreach ($cells as $col => $cell) {
            $isDateCell = ! empty($cell['isDate']);
            $text = $this->cellText($cell);
            $isDateString = preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text) === 1;

            if (! $isDateCell && ! $isDateString) {
                continue;
            }

            try {
                if ($isDateCell) {
                    $date = Carbon::instance(Date::excelToDateTimeObject((float) $cell['raw']));
                } else {
                    $date = Carbon::createFromFormat('d/m/Y', $text)->setTime(0, 0);
                }
            } catch (\Throwable $e) {
                $date = null;
            }

            if ($date) {
                $dateCol = $col;
                break;
            }
        }

        if (! $date || ! $dateCol) {
            return null;
        }

        $name = null;
        foreach ($cells as $col => $cell) {
            if (strcmp($col, $dateCol) <= 0) {
                continue;
            }
            $text = $this->cellText($cell);
            if ($text === '' || $this->isNumericText($text)) {
                continue;
            }
            $name = $text;
            break;
        }

        if ($name === null) {
            return null;
        }

        return ['date' => $date, 'name' => $name];
    }

    private function detectCustomerHeader(array $cells): ?array
    {
        $mapped = [];
        foreach ($cells as $col => $cell) {
            $key = $this->normalizeHeader($this->cellText($cell));
            if (isset(self::HEADER_ALIASES[$key])) {
                $mapped[self::HEADER_ALIASES[$key]] = $col;
            }
        }

        if (isset($mapped['no_sambungan'], $mapped['tagihan'], $mapped['nama'])) {
            return $mapped;
        }

        return null;
    }

    private function detailMessage(array $d): string
    {
        $parts = [];
        if ($d['no_sambungan'] !== '') {
            $parts[] = "No. Sambungan: {$d['no_sambungan']}";
        }
        if ($d['nama'] !== '') {
            $parts[] = "Nama: {$d['nama']}";
        }
        if (($d['wilayah'] ?? null) && $d['wilayah'] !== '' && $d['wilayah'] !== '-') {
            $parts[] = "Wilayah: {$d['wilayah']}";
        }
        $parts[] = $d['note'];

        return implode(' | ', $parts);
    }

    private function cellText(?array $cell): string
    {
        if (! $cell) {
            return '';
        }

        if ($cell['fmt'] !== '') {
            return $cell['fmt'];
        }

        $value = $cell['raw'] ?? null;
        if ($value === null) {
            return '';
        }

        if ($value instanceof \PhpOffice\PhpSpreadsheet\Cell\RichText) {
            return trim($value->getPlainText());
        }

        return trim((string) $value);
    }

    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->cellText($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isNumericText(string $text): bool
    {
        return preg_match('/^[\d.,\sRp\.\-]+$/u', $text) === 1;
    }

    private function toInt(?string $value): ?int
    {
        $digits = preg_replace('/[^0-9]/', '', $value ?? '');

        return $digits === '' ? null : (int) $digits;
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }

    private function normalizeNameKey(string $name): string
    {
        return mb_strtolower($this->normalizeName($name));
    }

    private function normalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z]/', '', mb_strtolower(trim($header)));
    }
}