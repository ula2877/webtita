<?php

namespace Tests\Feature;

use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PetugasImportTest extends TestCase
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

    private function petugas(string $name = 'Maulita Utami'): User
    {
        return User::create([
            'name' => $name,
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    private function makeExcel(array $names, string $sheetName = 'ketua kelompok'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        $sheet->fromArray(['no', 'nama'], null, 'A1');

        $row = 2;
        foreach ($names as $i => $name) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $name);
            $row++;
        }

        $path = sys_get_temp_dir() . '/petugas_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function file(string $path, string $name = 'data.xlsx'): UploadedFile
    {
        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_creates_petugas_users_from_ketua_kelompok_sheet(): void
    {
        $file = $this->makeExcel(['Irnita Agustin Putri, S.E.', 'Karina Impiana Sari, S.H.']);
        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)]);

        $response->assertStatus(201)
            ->assertJsonPath('sheet_name', 'ketua kelompok')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('created', 2)
            ->assertJsonPath('existing', 0)
            ->assertJsonPath('invalid', 0);

        $this->assertDatabaseCount('users', 3);
        $users = User::where('role', User::ROLE_PETUGAS)->get();
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertTrue((bool) $user->is_active);
            $this->assertTrue(Hash::check('password', $user->password));
        }

        $log = ImportLog::first();
        $this->assertNotNull($log);
        $this->assertNull($log->period_id);
        $this->assertSame('ketua kelompok', $log->sheet_name);
        $this->assertSame(2, $log->created_users);
        $this->assertSame(0, $log->existing_users);
    }

    public function test_second_import_skips_existing_users(): void
    {
        $names = ['Irnita Agustin Putri, S.E.', 'Maulita Utami', 'Zulfikur Aini Hisbulwaton'];
        $file = $this->makeExcel($names);
        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)])
            ->assertStatus(201)
            ->assertJsonPath('created', 3);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)])
            ->assertStatus(201)
            ->assertJsonPath('created', 0)
            ->assertJsonPath('existing', 3)
            ->assertJsonPath('invalid', 0);

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('import_logs', 2);
    }

    public function test_import_idempotent_method_uses_is_active_false_matching(): void
    {
        $this->petugas('Oktavia Siswi Purnamasari');

        $file = $this->makeExcel(['oktavia siswi purnamasari']);
        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)])
            ->assertStatus(201)
            ->assertJsonPath('created', 0)
            ->assertJsonPath('existing', 1);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_missing_sheet_returns_unprocessable(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('rekap wilayah')->fromArray(['NO', 'ALAMAT'], null, 'A1');
        $path = sys_get_temp_dir() . '/petugas_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $admin = $this->admin();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token($admin),
            'Accept' => 'application/json',
        ])
            ->post('/api/admin/import/petugas', ['file' => $this->file($path, 'bad.xlsx')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_empty_names_and_duplicates_are_counted_as_invalid(): void
    {
        $file = $this->makeExcel(['Ahmad Fauzi', '', '  ', 'Ahmad Fauzi']);
        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)]);

        $response->assertStatus(201)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('existing', 0)
            ->assertJsonPath('invalid', 3);

        $this->assertDatabaseCount('users', 2);

        $log = ImportLog::first();
        $this->assertSame(ImportLog::STATUS_PARTIAL, $log->status);
        $this->assertSame(3, $log->invalid_rows);
    }

    public function test_case_and_whitespace_insensitive_matching(): void
    {
        $this->petugas('Maulita Utami');

        $file = $this->makeExcel(['  MAULITA  UTAMI ']);
        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)]);

        $response->assertStatus(201)
            ->assertJsonPath('created', 0)
            ->assertJsonPath('existing', 1)
            ->assertJsonPath('invalid', 0);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_import_does_not_delete_existing_users(): void
    {
        $keep = $this->petugas('Petugas Tidak Ada di File');

        $file = $this->makeExcel(['Ahmad Fauzi']);
        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)])
            ->assertStatus(201);

        $this->assertDatabaseHas('users', ['id' => $keep->id, 'name' => 'Petugas Tidak Ada di File']);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_requires_admin_role(): void
    {
        $petugas = $this->petugas();
        $file = $this->makeExcel(['Ahmad Fauzi']);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->post('/api/admin/import/petugas', ['file' => $this->file($file)])
            ->assertForbidden();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }
}