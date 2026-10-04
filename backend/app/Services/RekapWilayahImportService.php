<?php

namespace App\Services;

use App\Models\ImportLog;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapWilayahImportService
{
    private const SHEET_NAME = 'rekap wilayah';

    private const HEADER_ALIASES = [
        'alamat' => 'alamat',
        'id' => 'id',
        'no' => 'no',
        'nomor' => 'no',
        'timpengagih' => 'petugas',
        'timpenagih' => 'petugas',
        'penagih' => 'petugas',
        'petugas' => 'petugas',
        'namapetugas' => 'petugas',
    ];

    public function import(string $filePath, int $uploadedBy, ?string $originalFileName = null): array
    {
        $spreadsheet = IOFactory::load($filePath);

        $sheet = $this->findSheet($spreadsheet);
        if (! $sheet) {
            throw ValidationException::withMessages([
                'file' => ['Sheet "rekap wilayah" tidak ditemukan pada file Excel.'],
            ]);
        }

        $rows = $sheet->toArray(null, false, false, true);

        $header = $this->locateHeaders($rows);
        if (! $header || ! isset($header['mapped']['id'], $header['mapped']['alamat'], $header['mapped']['petugas'])) {
            throw ValidationException::withMessages([
                'file' => ['Struktur kolom tidak sesuai. Wajib ada kolom: ID, ALAMAT, dan TIM PENAGIH pada sheet "rekap wilayah".'],
            ]);
        }

        $mapped = $header['mapped'];
        $headerRow = $header['row'];
        $petugasIndex = $this->petugasIndex();

        $plan = [];
        $seenCode = [];
        $total = 0;

        foreach ($rows as $rowNumber => $cols) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            if ($this->isEmptyRow($cols)) {
                continue;
            }

            $total++;

            $code = $this->rawString($cols[$mapped['id']] ?? null);
            $nama = $this->normalizeName($this->cellToString($cols[$mapped['alamat']] ?? null));
            $penagih = $this->normalizeName($this->cellToString($cols[$mapped['petugas']] ?? null));

            if ($code === '' || $nama === '' || $penagih === '') {
                $plan[] = [
                    'row' => $rowNumber,
                    'code' => $code,
                    'nama' => $nama,
                    'penagih' => $penagih,
                    'status' => 'invalid',
                    'note' => $this->validationNote($code, $nama, $penagih),
                ];
                continue;
            }

            if (isset($seenCode[$code])) {
                $plan[] = [
                    'row' => $rowNumber,
                    'code' => $code,
                    'nama' => $nama,
                    'penagih' => $penagih,
                    'status' => 'invalid',
                    'note' => "Kode wilayah \"{$code}\" duplikat dalam file (baris {$seenCode[$code]}), dilewati.",
                ];
                continue;
            }
            $seenCode[$code] = $rowNumber;

            $petugas = $petugasIndex[$this->normalizeNameKey($penagih)] ?? null;
            if (! $petugas) {
                $plan[] = [
                    'row' => $rowNumber,
                    'code' => $code,
                    'nama' => $nama,
                    'penagih' => $penagih,
                    'status' => 'invalid',
                    'note' => 'Petugas tidak ditemukan.',
                ];
                continue;
            }

            $plan[] = [
                'row' => $rowNumber,
                'code' => $code,
                'nama' => $nama,
                'penagih' => $petugas->name,
                'petugas_id' => $petugas->id,
                'status' => 'upsert',
            ];
        }

        $created = 0;
        $updated = 0;
        $invalid = 0;
        $details = [];

        DB::transaction(function () use ($plan, $uploadedBy, $originalFileName, $sheet, $filePath, &$total, &$created, &$updated, &$invalid, &$details) {
            foreach ($plan as $entry) {
                if ($entry['status'] !== 'upsert') {
                    $invalid++;
                    $details[] = [
                        'row' => $entry['row'],
                        'code' => $entry['code'],
                        'nama' => $entry['nama'],
                        'penagih' => $entry['penagih'],
                        'status' => 'invalid',
                        'note' => $entry['note'],
                    ];
                    continue;
                }

                $wilayah = Wilayah::updateOrCreate(
                    ['code' => $entry['code']],
                    ['name' => $entry['nama'], 'petugas_id' => $entry['petugas_id']]
                );

                if ($wilayah->wasRecentlyCreated) {
                    $created++;
                    $details[] = [
                        'row' => $entry['row'],
                        'code' => $entry['code'],
                        'nama' => $entry['nama'],
                        'penagih' => $entry['penagih'],
                        'status' => 'created',
                        'note' => 'Wilayah baru dibuat.',
                    ];
                } else {
                    $updated++;
                    $details[] = [
                        'row' => $entry['row'],
                        'code' => $entry['code'],
                        'nama' => $entry['nama'],
                        'penagih' => $entry['penagih'],
                        'status' => 'updated',
                        'note' => 'Wilayah diperbarui.',
                    ];
                }
            }

            $status = $invalid > 0
                ? ImportLog::STATUS_PARTIAL
                : ($created === 0 && $updated === 0 ? ImportLog::STATUS_FAILED : ImportLog::STATUS_SUCCESS);

            ImportLog::create([
                'period_id' => null,
                'uploaded_by' => $uploadedBy,
                'file_name' => $originalFileName ?: basename($filePath),
                'sheet_name' => $sheet->getTitle(),
                'total_rows' => $total,
                'success_rows' => $created + $updated,
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
            'message' => 'Sinkronisasi wilayah selesai.',
            'sheet_name' => $sheet->getTitle(),
            'total' => $total,
            'created' => $created,
            'updated' => $updated,
            'invalid' => $invalid,
            'details' => $details,
        ];
    }

    private function findSheet($spreadsheet): ?Worksheet
    {
        $targetKey = $this->normalizeNameKey(self::SHEET_NAME);

        foreach ($spreadsheet->getSheetNames() as $name) {
            if ($this->normalizeNameKey($name) === $targetKey) {
                return $spreadsheet->getSheetByName($name);
            }
        }

        return null;
    }

    private function locateHeaders(array $rows): ?array
    {
        foreach ($rows as $rowNumber => $cols) {
            $mapped = [];
            foreach ($cols as $letter => $value) {
                $key = $this->normalizeHeader((string) $value);
                if (isset(self::HEADER_ALIASES[$key])) {
                    $mapped[self::HEADER_ALIASES[$key]] = $letter;
                }
            }

            if (isset($mapped['id'], $mapped['alamat'], $mapped['petugas'])) {
                return ['row' => $rowNumber, 'mapped' => $mapped];
            }
        }

        return null;
    }

    private function petugasIndex(): array
    {
        return User::where('role', User::ROLE_PETUGAS)
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user) => [$this->normalizeNameKey($user->name) => $user])
            ->all();
    }

    private function validationNote(string $code, string $nama, string $penagih): string
    {
        $missing = [];
        if ($code === '') {
            $missing[] = 'ID';
        }
        if ($nama === '') {
            $missing[] = 'ALAMAT';
        }
        if ($penagih === '') {
            $missing[] = 'TIM PENAGIH';
        }

        return 'Kolom ' . implode(', ', $missing) . ' kosong, baris dilewati.';
    }

    private function detailMessage(array $d): string
    {
        $parts = [];
        if ($d['code'] !== '') {
            $parts[] = "ID: {$d['code']}";
        }
        if ($d['nama'] !== '') {
            $parts[] = "Wilayah: {$d['nama']}";
        }
        if ($d['penagih'] !== '') {
            $parts[] = "Penagih: {$d['penagih']}";
        }
        $parts[] = $d['note'];

        return implode(' | ', $parts);
    }

    private function rawString($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \PhpOffice\PhpSpreadsheet\Cell\RichText) {
            return trim($value->getPlainText());
        }

        if (is_float($value)) {
            return (string) $value;
        }

        return trim((string) $value);
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

    private function cellToString($value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \PhpOffice\PhpSpreadsheet\Cell\RichText) {
            return $value->getPlainText();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-m-Y');
        }

        return (string) $value;
    }

    private function isEmptyRow(array $cols): bool
    {
        foreach ($cols as $value) {
            if ($value !== null && trim($this->cellToString($value)) !== '') {
                return false;
            }
        }

        return true;
    }
}