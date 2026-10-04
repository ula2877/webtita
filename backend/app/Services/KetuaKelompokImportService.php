<?php

namespace App\Services;

use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KetuaKelompokImportService
{
    private const SHEET_NAME = 'ketua kelompok';
    private const COLUMN_NAMES = ['nama'];

    public function import(string $filePath, int $uploadedBy, ?string $originalFileName = null): array
    {
        $spreadsheet = IOFactory::load($filePath);

        $sheet = $this->findSheet($spreadsheet);
        if (! $sheet) {
            throw ValidationException::withMessages([
                'file' => ['Sheet "ketua kelompok" tidak ditemukan pada file Excel.'],
            ]);
        }

        $rows = $sheet->toArray(null, false, true, true);

        [$headerRow, $nameColumn] = $this->locateNameColumn($rows);
        if (! $nameColumn) {
            throw ValidationException::withMessages([
                'file' => ['Kolom "nama" tidak ditemukan pada sheet "ketua kelompok".'],
            ]);
        }

        $existingUsers = $this->existingUsersByNormalizedName();

        $plan = [];
        $seenInFile = [];
        $total = 0;

        foreach ($rows as $rowNumber => $cols) {
            if ($rowNumber <= $headerRow) {
                continue;
            }

            if ($this->isEmptyRow($cols)) {
                continue;
            }

            $total++;

            $name = $this->normalizeName($this->cellToString($cols[$nameColumn] ?? null));

            if ($name === '') {
                $plan[] = [
                    'row' => $rowNumber,
                    'name' => null,
                    'status' => 'invalid',
                    'note' => 'Baris dilewati karena nama kosong.',
                ];
                continue;
            }

            $key = $this->normalizeNameKey($name);

            if (isset($seenInFile[$key])) {
                $plan[] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'status' => 'invalid',
                    'note' => 'Nama duplikat dalam file, dilewati.',
                ];
                continue;
            }
            $seenInFile[$key] = $rowNumber;

            if (isset($existingUsers[$key])) {
                $plan[] = [
                    'row' => $rowNumber,
                    'name' => $name,
                    'status' => 'existing',
                    'note' => 'User sudah ada.',
                ];
                continue;
            }

            $plan[] = [
                'row' => $rowNumber,
                'name' => $name,
                'status' => 'create',
            ];
        }

        $created = 0;
        $existing = 0;
        $invalid = 0;
        $details = [];

        DB::transaction(function () use ($plan, $uploadedBy, $originalFileName, $sheet, $filePath, &$created, &$existing, &$invalid, &$details, &$total) {
            foreach ($plan as $entry) {
                if ($entry['status'] === 'create') {
                    User::create([
                        'name' => $entry['name'],
                        'password' => Hash::make('password'),
                        'role' => User::ROLE_PETUGAS,
                        'is_active' => true,
                    ]);

                    $created++;
                    $details[] = [
                        'row' => $entry['row'],
                        'name' => $entry['name'],
                        'status' => 'created',
                        'note' => 'User baru dibuat.',
                    ];

                    continue;
                }

                if ($entry['status'] === 'existing') {
                    $existing++;
                    $details[] = [
                        'row' => $entry['row'],
                        'name' => $entry['name'],
                        'status' => 'existing',
                        'note' => 'User sudah ada.',
                    ];

                    continue;
                }

                $invalid++;
                $details[] = [
                    'row' => $entry['row'],
                    'name' => $entry['name'],
                    'status' => 'invalid',
                    'note' => $entry['note'],
                ];
            }

            $status = $invalid > 0
                ? ImportLog::STATUS_PARTIAL
                : ($created === 0 && $existing === 0 ? ImportLog::STATUS_FAILED : ImportLog::STATUS_SUCCESS);

            ImportLog::create([
                'period_id' => null,
                'uploaded_by' => $uploadedBy,
                'file_name' => $originalFileName ?: basename($filePath),
                'sheet_name' => $sheet->getTitle(),
                'total_rows' => $total,
                'success_rows' => $created,
                'failed_rows' => $invalid,
                'created_users' => $created,
                'existing_users' => $existing,
                'invalid_rows' => $invalid,
                'status' => $status,
                'error_details' => array_map(fn (array $d) => [
                    'row' => $d['row'],
                    'type' => $d['status'] === 'invalid' ? 'error' : 'success',
                    'message' => $d['name']
                        ? "{$d['name']}: {$d['note']}"
                        : $d['note'],
                ], $details),
            ]);
        });

        return [
            'message' => 'Sinkronisasi petugas selesai.',
            'sheet_name' => $sheet->getTitle(),
            'total' => $total,
            'created' => $created,
            'existing' => $existing,
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

    private function locateNameColumn(array $rows): array
    {
        foreach ($rows as $rowNumber => $cols) {
            foreach ($cols as $letter => $value) {
                if (in_array($this->normalizeNameKey($this->cellToString($value)), self::COLUMN_NAMES, true)) {
                    return [$rowNumber, $letter];
                }
            }
        }

        return [null, null];
    }

    private function existingUsersByNormalizedName(): array
    {
        return User::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user) => [$this->normalizeNameKey($user->name) => $user->name])
            ->all();
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }

    private function normalizeNameKey(string $name): string
    {
        return mb_strtolower($this->normalizeName($name));
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