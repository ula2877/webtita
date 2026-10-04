<?php

namespace Tests\Feature;

use App\Models\ImportLog;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class WilayahImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function petugas(string $name): User
    {
        return User::create([
            'name' => $name,
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    private function makeWorkbook(
        array $ketuaNames,
        array $rekapRows,
        string $rekapSheetName = 'rekap wilayah',
        bool $withDetail = true,
    ): string {
        $spreadsheet = new Spreadsheet();

        $ketua = $spreadsheet->getActiveSheet();
        $ketua->setTitle('ketua kelompok');
        $ketua->fromArray(['no', 'nama'], null, 'A1');
        $row = 2;
        foreach ($ketuaNames as $i => $name) {
            $ketua->setCellValue("A{$row}", $i + 1);
            $ketua->setCellValue("B{$row}", $name);
            $row++;
        }

        $rekap = $spreadsheet->createSheet();
        $rekap->setTitle($rekapSheetName);
        $rekap->fromArray(['NO', 'ALAMAT', 'ID', 'TIM PENAGIH'], null, 'A1');
        $row = 2;
        foreach ($rekapRows as $data) {
            $rekap->fromArray($data, null, "A{$row}");
            $row++;
        }

        if ($withDetail) {
            $detail = $spreadsheet->createSheet();
            $detail->setTitle('detail tagihan');
            // Kelompok tanpa pelanggan: cukup tanggal + header agar sheet valid.
            $detail->setCellValue('B1', Date::PHPToExcel(new \DateTime('2026-01-15')));
            $detail->getStyle('B1')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $detail->setCellValue('C1', 'SECTION VALIDATION');
            $detail->fromArray(['NO', 'NO.SAMBUNGAN', 'NAMA', 'ALAMAT', 'BLN', 'TAGIHAN'], null, 'A2');
        }

        $path = sys_get_temp_dir() . '/wilayah_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function file(string $path): UploadedFile
    {
        return new UploadedFile($path, 'data.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_combined_import_creates_petugas_then_wilayah(): void
    {
        $file = $this->makeWorkbook(
            ['Irnita Agustin Putri, S.E.', 'Karina Impiana Sari, S.H.'],
            [
                ['1', 'A. FAQIH', '1.1', 'Karina Impiana Sari, S.H.'],
                ['2', 'ANGGREK', '1.2', 'Irnita Agustin Putri, S.E.'],
            ],
        );

        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)]);

        $response->assertStatus(201)
            ->assertJsonPath('petugas.created', 2)
            ->assertJsonPath('petugas.sheet_name', 'ketua kelompok')
            ->assertJsonPath('wilayah.created', 2)
            ->assertJsonPath('wilayah.updated', 0)
            ->assertJsonPath('wilayah.invalid', 0);

        $karina = User::where('name', 'Karina Impiana Sari, S.H.')->first();
        $irnita = User::where('name', 'Irnita Agustin Putri, S.E.')->first();
        $this->assertTrue(Hash::check('password', $karina->password));
        $this->assertDatabaseHas('wilayah', ['code' => '1.1', 'name' => 'A. FAQIH', 'petugas_id' => $karina->id]);
        $this->assertDatabaseHas('wilayah', ['code' => '1.2', 'name' => 'ANGGREK', 'petugas_id' => $irnita->id]);

        $log = ImportLog::where('sheet_name', 'rekap wilayah')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->period_id);
        $this->assertSame(2, $log->success_rows);
    }

    public function test_second_import_upserts_by_code_without_duplicates(): void
    {
        $file = $this->makeWorkbook(
            ['Karina Impiana Sari, S.H.'],
            [
                ['1', 'A. FAQIH', '1.1', 'Karina Impiana Sari, S.H.'],
                ['2', 'ANGGREK', '1.2', 'Karina Impiana Sari, S.H.'],
            ],
        );

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)])
            ->assertStatus(201)
            ->assertJsonPath('wilayah.created', 2);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)])
            ->assertStatus(201)
            ->assertJsonPath('wilayah.created', 0)
            ->assertJsonPath('wilayah.updated', 2)
            ->assertJsonPath('wilayah.invalid', 0);

        $this->assertDatabaseCount('wilayah', 2);
    }

    public function test_import_updates_petugas_ownership_on_existing_code(): void
    {
        $irnita = $this->petugas('Irnita Agustin Putri, S.E.');
        $maulita = $this->petugas('Maulita Utami');
        Wilayah::create(['code' => '1.1', 'name' => 'A. FAQIH', 'petugas_id' => $irnita->id]);

        $file = $this->makeWorkbook(
            [],
            [['1', 'A. FAQIH', '1.1', 'Maulita Utami']],
        );

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)])
            ->assertStatus(201)
            ->assertJsonPath('wilayah.created', 0)
            ->assertJsonPath('wilayah.updated', 1)
            ->assertJsonPath('wilayah.invalid', 0)
            ->assertJsonPath('petugas.invalid', 0);

        $this->assertDatabaseHas('wilayah', ['code' => '1.1', 'petugas_id' => $maulita->id]);
        $this->assertDatabaseCount('wilayah', 1);
    }

    public function test_unknown_petugas_is_invalid_and_not_created_fallback(): void
    {
        $file = $this->makeWorkbook(
            ['Karina Impiana Sari, S.H.'],
            [
                ['1', 'A. FAQIH', '1.1', 'Karina Impiana Sari, S.H.'],
                ['2', 'ANGGREK', '1.2', 'Nama tidak dikenal'],
            ],
        );

        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)]);

        $response->assertStatus(201)
            ->assertJsonPath('wilayah.created', 1)
            ->assertJsonPath('wilayah.invalid', 1)
            ->assertJsonPath('wilayah.details.1.status', 'invalid')
            ->assertJsonPath('wilayah.details.1.note', 'Petugas tidak ditemukan.');

        $this->assertDatabaseCount('wilayah', 1);
        $this->assertDatabaseMissing('users', ['name' => 'Nama tidak dikenal']);

        $log = ImportLog::where('sheet_name', 'rekap wilayah')->first();
        $this->assertSame(ImportLog::STATUS_PARTIAL, $log->status);
        $this->assertSame(1, $log->failed_rows);
    }

    public function test_missing_rekap_sheet_returns_unprocessable(): void
    {
        $file = $this->makeWorkbook(
            ['Karina Impiana Sari, S.H.'],
            [],
            'detail tagihan',
            false,
        );

        $admin = $this->admin();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token($admin),
            'Accept' => 'application/json',
        ])
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_missing_penagih_column_returns_unprocessable(): void
    {
        $spreadsheet = new Spreadsheet();
        $rekap = $spreadsheet->getActiveSheet()->setTitle('rekap wilayah');
        $rekap->fromArray(['NO', 'ALAMAT', 'ID'], null, 'A1');

        $path = sys_get_temp_dir() . '/wilayah_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $admin = $this->admin();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token($admin),
            'Accept' => 'application/json',
        ])
            ->post('/api/admin/import/wilayah', ['file' => $this->file($path)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_missing_penagih_cell_is_invalid(): void
    {
        $file = $this->makeWorkbook(
            ['Karina Impiana Sari, S.H.'],
            [
                ['1', 'A. FAQIH', '1.1', 'Karina Impiana Sari, S.H.'],
                ['2', 'ANGGREK', '1.2', ''],
                ['3', '', '1.3', 'Karina Impiana Sari, S.H.'],
                ['4', 'TOHA', '', 'Karina Impiana Sari, S.H.'],
            ],
        );

        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)]);

        $response->assertStatus(201)
            ->assertJsonPath('wilayah.total', 4)
            ->assertJsonPath('wilayah.created', 1)
            ->assertJsonPath('wilayah.invalid', 3);

        $this->assertDatabaseCount('wilayah', 1);
    }

    public function test_duplicate_code_in_file_is_invalid(): void
    {
        $file = $this->makeWorkbook(
            ['Karina Impiana Sari, S.H.'],
            [
                ['1', 'A. FAQIH', '1.1', 'Karina Impiana Sari, S.H.'],
                ['2', 'COKRO', '1.1', 'Karina Impiana Sari, S.H.'],
            ],
        );

        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)]);

        $response->assertStatus(201)
            ->assertJsonPath('wilayah.created', 1)
            ->assertJsonPath('wilayah.invalid', 1)
            ->assertJsonPath('wilayah.details.0.code', '1.1');

        $this->assertDatabaseCount('wilayah', 1);
        $this->assertDatabaseMissing('wilayah', ['name' => 'COKRO']);
    }

    public function test_code_preserved_as_string_with_trailing_zero(): void
    {
        $spreadsheet = new Spreadsheet();

        $ketua = $spreadsheet->getActiveSheet();
        $ketua->setTitle('ketua kelompok');
        $ketua->fromArray(['no', 'nama'], null, 'A1');
        $ketua->fromArray(['1', 'Karina Impiana Sari, S.H.'], null, 'A2');

        $rekap = $spreadsheet->createSheet()->setTitle('rekap wilayah');
        $rekap->fromArray(['NO', 'ALAMAT', 'ID', 'TIM PENAGIH'], null, 'A1');
        $rekap->setCellValue('A2', 1);
        $rekap->setCellValue('B2', 'WILAYAH SATU');
        $rekap->getCell('C2')->setValueExplicit('1.10', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $rekap->setCellValue('D2', 'Karina Impiana Sari, S.H.');

        $detail = $spreadsheet->createSheet();
        $detail->setTitle('detail tagihan');
        $detail->setCellValue('B1', Date::PHPToExcel(new \DateTime('2026-01-15')));
        $detail->getStyle('B1')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $detail->setCellValue('C1', 'SECTION VALIDATION');
        $detail->fromArray(['NO', 'NO.SAMBUNGAN', 'NAMA', 'ALAMAT', 'BLN', 'TAGIHAN'], null, 'A2');

        $path = sys_get_temp_dir() . '/wilayah_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($path)])
            ->assertStatus(201)
            ->assertJsonPath('wilayah.created', 1);

        $this->assertDatabaseHas('wilayah', ['code' => '1.10', 'name' => 'WILAYAH SATU']);
    }

    public function test_sheet_position_is_irrelevant(): void
    {
        $spreadsheet = new Spreadsheet();
        $tagihan = $spreadsheet->getActiveSheet();
        $tagihan->setTitle('detail tagihan');
        $tagihan->setCellValue('A1', 'NO');

        $rekap = $spreadsheet->createSheet()->setTitle('rekap wilayah');
        $rekap->fromArray(['NO', 'ALAMAT', 'ID', 'TIM PENAGIH'], null, 'A1');
        $rekap->fromArray(['1', 'TOHA', '1.4', 'Maulita Utami'], null, 'A2');

        $ketua = $spreadsheet->createSheet()->setTitle('ketua kelompok');
        $ketua->fromArray(['no', 'nama'], null, 'A1');
        $ketua->fromArray(['1', 'Maulita Utami'], null, 'A2');

        $path = sys_get_temp_dir() . '/wilayah_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($path)])
            ->assertStatus(201)
            ->assertJsonPath('petugas.created', 1)
            ->assertJsonPath('wilayah.created', 1);

        $this->assertDatabaseHas('wilayah', ['code' => '1.4', 'name' => 'TOHA']);
    }

    public function test_import_does_not_delete_other_wilayah(): void
    {
        $petugas = $this->petugas('Karina Impiana Sari, S.H.');
        Wilayah::create(['code' => '9.9', 'name' => 'KAMPUNG LAMA', 'petugas_id' => $petugas->id]);

        $file = $this->makeWorkbook(
            [],
            [['1', 'A. FAQIH', '1.1', 'Karina Impiana Sari, S.H.']],
        );

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)])
            ->assertStatus(201);

        $this->assertDatabaseHas('wilayah', ['code' => '9.9', 'name' => 'KAMPUNG LAMA']);
        $this->assertDatabaseCount('wilayah', 2);
    }

    public function test_petugas_role_cannot_import_wilayah(): void
    {
        $file = $this->makeWorkbook(['Karina'], [['1', 'TOHA', '1.4', 'Karina']]);
        $petugas = $this->petugas('Karina');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($file)])
            ->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }
}