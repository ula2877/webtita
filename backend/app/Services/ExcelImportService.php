<?php

namespace App\Services;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\ImportLog;
use App\Models\Period;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportService
{
    private const COLUMN_ALIASES = [
        'nosambungan' => 'no_sambungan',
        'nosamb' => 'no_sambungan',
        'no' => 'no_sambungan',
        'namapelanggan' => 'nama',
        'nama' => 'nama',
        'jumlahbulantunggakan' => 'jumlah_bulan_tunggakan',
        'bulantunggakan' => 'jumlah_bulan_tunggakan',
        'petugas' => 'petugas',
        'namapetugas' => 'petugas',
    ];

    public function import(string $filePath, int $periodId, int $uploadedBy, bool $replace = false): ImportLog
    {
        $period = Period::findOrFail($periodId);

        $importLog = ImportLog::create([
            'period_id' => $period->id,
            'uploaded_by' => $uploadedBy,
            'file_name' => basename($filePath),
            'total_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'status' => ImportLog::STATUS_PROCESSING,
            'error_details' => [],
        ]);

        try {
            [$rows, $mappedHeaders] = $this->readRows($filePath);

            $errors = [];
            $successCount = 0;
            $petugasIndex = $this->buildPetugasIndex();
            $seenInFile = [];

            DB::transaction(function () use ($rows, $mappedHeaders, $period, $importLog, $replace, &$errors, &$successCount, $petugasIndex, &$seenInFile) {
                foreach ($rows as $rowNumber => $cols) {
                    $lineErrors = $this->validateRow($cols, $mappedHeaders, $rowNumber, $seenInFile);

                    if (! empty($lineErrors)) {
                        foreach ($lineErrors as $message) {
                            $errors[] = ['row' => $rowNumber, 'type' => 'error', 'message' => $message];
                        }
                        continue;
                    }

                    $values = $this->extractValues($cols, $mappedHeaders);

                    $petugas = $this->resolvePetugas($values['petugas'], $petugasIndex);
                    if (! $petugas) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'type' => 'warning',
                            'message' => "Petugas \"{$values['petugas']}\" tidak ditemukan. Data tetap diimport tanpa penugasan.",
                        ];
                    }

                    $customer = Customer::firstOrNew(['no_sambungan' => $values['no_sambungan']]);
                    $customer->nama = $values['nama'];
                    $customer->save();

                    Arrear::updateOrCreate(
                        ['customer_id' => $customer->id, 'period_id' => $period->id],
                        [
                            'jumlah_bulan_tunggakan' => $values['jumlah_bulan_tunggakan'],
                            'petugas_id' => $petugas?->id,
                        ]
                    );

                    $successCount++;
                }

                if ($replace) {
                    $this->removeAbsentUnvisitedArrears($period, $seenInFile);
                }
            });

            $status = $successCount === count($rows)
                ? ImportLog::STATUS_SUCCESS
                : ($successCount === 0 ? ImportLog::STATUS_FAILED : ImportLog::STATUS_PARTIAL);

            $importLog->update([
                'total_rows' => count($rows),
                'success_rows' => $successCount,
                'failed_rows' => count($rows) - $successCount,
                'status' => $status,
                'error_details' => $errors,
            ]);

            return $importLog;
        } catch (\Throwable $e) {
            $importLog->update([
                'status' => ImportLog::STATUS_FAILED,
                'error_details' => [[
                    'row' => null,
                    'type' => 'error',
                    'message' => 'Terjadi kesalahan fatal saat memproses file: ' . $e->getMessage(),
                ]],
            ]);

            throw ValidationException::withMessages([
                'file' => ['Import gagal: ' . $e->getMessage()],
            ]);
        }
    }

    private function readRows(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, false, true, true);

        $headerKeys = [];

        foreach ($rows as $rowNumber => $cols) {
            $mapped = [];
            foreach ($cols as $letter => $value) {
                $key = $this->normalizeHeader((string) $value);
                if (isset(self::COLUMN_ALIASES[$key])) {
                    $mapped[self::COLUMN_ALIASES[$key]] = $letter;
                }
            }

            $required = ['no_sambungan', 'nama', 'jumlah_bulan_tunggakan'];
            if (count(array_intersect($required, array_keys($mapped))) === count($required)) {
                $headerKeys = $mapped;
                $headerRow = $rowNumber;
                break;
            }
        }

        if (empty($headerKeys)) {
            throw ValidationException::withMessages([
                'file' => ['Struktur kolom tidak sesuai. Wajib ada kolom: No Sambungan, Nama Pelanggan, Jumlah Bulan Tunggakan (dan opsional Petugas).'],
            ]);
        }

        $dataRows = [];
        foreach ($rows as $rowNumber => $cols) {
            if ($rowNumber <= $headerRow) {
                continue;
            }
            if ($this->isEmptyRow($cols)) {
                continue;
            }
            $dataRows[$rowNumber] = $cols;
        }

        return [$dataRows, $headerKeys];
    }

    private function isEmptyRow(array $cols): bool
    {
        foreach ($cols as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z]/', '', strtolower(trim($header)));
    }

    private function validateRow(array $cols, array $mappedHeaders, int $rowNumber, array &$seenInFile): array
    {
        $errors = [];

        $noSambungan = $this->rawValue($cols, $mappedHeaders, 'no_sambungan');
        if ($noSambungan === null || trim((string) $noSambungan) === '') {
            $errors[] = 'No Sambungan kosong';
        } else {
            $noSambungan = (string) $noSambungan;
            if (isset($seenInFile[$noSambungan])) {
                $errors[] = "No Sambungan \"{$noSambungan}\" duplikat dalam file (baris {$seenInFile[$noSambungan]})";
            } else {
                $seenInFile[$noSambungan] = $rowNumber;
            }
        }

        $nama = $this->rawValue($cols, $mappedHeaders, 'nama');
        if ($nama === null || trim((string) $nama) === '') {
            $errors[] = 'Nama Pelanggan kosong';
        }

        $bulan = $this->rawValue($cols, $mappedHeaders, 'jumlah_bulan_tunggakan');
        if ($bulan === null || ! is_numeric($bulan) || (int) $bulan < 1 || (float) $bulan !== (float) (int) $bulan) {
            $errors[] = 'Jumlah bulan tunggakan tidak valid (harus angka bulat positif)';
        }

        return $errors;
    }

    private function extractValues(array $cols, array $mappedHeaders): array
    {
        $noSambungan = (string) $this->rawValue($cols, $mappedHeaders, 'no_sambungan');
        $petugas = trim((string) ($this->rawValue($cols, $mappedHeaders, 'petugas') ?? ''));

        return [
            'no_sambungan' => $noSambungan,
            'nama' => trim((string) $this->rawValue($cols, $mappedHeaders, 'nama')),
            'jumlah_bulan_tunggakan' => (int) $this->rawValue($cols, $mappedHeaders, 'jumlah_bulan_tunggakan'),
            'petugas' => $petugas,
        ];
    }

    private function rawValue(array $cols, array $mappedHeaders, string $key)
    {
        $letter = $mappedHeaders[$key] ?? null;
        if (! $letter) {
            return null;
        }

        $value = $cols[$letter] ?? null;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-m-Y');
        }

        if ($value instanceof \PhpOffice\PhpSpreadsheet\Cell\RichText) {
            return $value->getPlainText();
        }

        if (is_float($value)) {
            return (string) (int) $value;
        }

        return $value;
    }

    private function buildPetugasIndex(): \Illuminate\Support\Collection
    {
        return User::where('role', User::ROLE_PETUGAS)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn ($u) => [strtolower(trim($u->name)) => $u]);
    }

    private function resolvePetugas(?string $name, $index): ?User
    {
        if (! $name) {
            return null;
        }

        return $index->get(strtolower($name));
    }

    private function removeAbsentUnvisitedArrears(Period $period, array $keptKeys): void
    {
        $periodArrears = Arrear::where('period_id', $period->id)->with('visit', 'customer')->get();

        foreach ($periodArrears as $arrear) {
            if ($arrear->visit !== null) {
                continue;
            }

            if (! isset($keptKeys[$arrear->customer->no_sambungan])) {
                $arrear->delete();
            }
        }
    }
}
