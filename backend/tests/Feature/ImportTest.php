<?php

namespace Tests\Feature;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\ImportLog;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['No Sambungan', 'Nama Pelanggan', 'Jumlah Bulan Tunggakan', 'Petugas'];

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function makeExcel(array $rows, array $headers = self::HEADERS): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        $row = 2;
        foreach ($rows as $data) {
            $sheet->fromArray($data, null, "A{$row}");
            $row++;
        }

        $path = sys_get_temp_dir() . '/import_test_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_admin_imports_valid_excel(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);
        $petugas = User::create([
            'name' => 'Ahmad Fauzi',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);

        $file = $this->makeExcel([
            ['00123456', 'Budi Santoso', 3, 'Ahmad Fauzi'],
            ['00123457', 'Siti Aminah', 5, 'Ahmad Fauzi'],
        ]);

        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import', [
                'period_id' => $period->id,
                'file' => new \Illuminate\Http\UploadedFile($file, 'tunggakan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.total_rows', 2)
            ->assertJsonPath('data.success_rows', 2)
            ->assertJsonPath('data.failed_rows', 0)
            ->assertJsonPath('data.status', ImportLog::STATUS_SUCCESS);

        $this->assertDatabaseHas('customers', ['no_sambungan' => '00123456', 'nama' => 'Budi Santoso']);
        $this->assertDatabaseCount('arrears', 2);
        $this->assertDatabaseHas('arrears', ['petugas_id' => $petugas->id]);
    }

    public function test_import_reports_invalid_rows_and_skips_them(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);

        $file = $this->makeExcel([
            ['00123456', 'Budi Santoso', 3, ''],
            ['', 'Siti Aminah', 5, ''],
            ['00123458', 'Joko', 'abc', ''],
            ['00123459', 'Cahyo', 2, ''],
        ]);

        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import', [
                'period_id' => $period->id,
                'file' => new \Illuminate\Http\UploadedFile($file, 'tunggakan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.total_rows', 4)
            ->assertJsonPath('data.success_rows', 2)
            ->assertJsonPath('data.failed_rows', 2)
            ->assertJsonPath('data.status', ImportLog::STATUS_PARTIAL);

        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('arrears', 2);

        $messages = $response->json('data.error_details');
        $this->assertNotEmpty(array_filter($messages, fn ($e) => str_contains($e['message'], 'No Sambungan kosong')));
        $this->assertNotEmpty(array_filter($messages, fn ($e) => str_contains($e['message'], 'Jumlah bulan tunggakan tidak valid')));
    }

    public function test_import_with_unknown_petugas_keeps_data_unassigned(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);

        $file = $this->makeExcel([
            ['00123456', 'Budi Santoso', 3, 'Petugas Tidak Ada'],
        ]);

        $admin = $this->admin();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import', [
                'period_id' => $period->id,
                'file' => new \Illuminate\Http\UploadedFile($file, 'tunggakan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.success_rows', 1);

        $this->assertDatabaseHas('arrears', ['petugas_id' => null]);
        $messages = $response->json('data.error_details');
        $this->assertNotEmpty(array_filter($messages, fn ($e) => $e['type'] === 'warning' && str_contains($e['message'], 'tidak ditemukan')));
    }

    public function test_import_to_populated_period_requires_replace(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);
        $customer = Customer::create(['no_sambungan' => '00123456', 'nama' => 'Budi']);
        Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);

        $file = $this->makeExcel([
            ['00123456', 'Budi Santoso', 3, ''],
        ]);

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import', [
                'period_id' => $period->id,
                'file' => new \Illuminate\Http\UploadedFile($file, 'tunggakan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertStatus(409)
            ->assertJsonPath('replace_required', true);
    }

    public function test_replace_keeps_visited_arrears(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);
        $kept = Customer::create(['no_sambungan' => '001', 'nama' => 'Sudah Dikunjungi']);
        $removed = Customer::create(['no_sambungan' => '002', 'nama' => 'Belum Dikunjungi']);

        $keptArrear = Arrear::create([
            'customer_id' => $kept->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);
        $keptArrear->visit()->create([
            'petugas_id' => $this->petugas()->id,
            'status_kunjungan' => 'ada_orang',
            'visited_at' => now(),
        ]);

        Arrear::create([
            'customer_id' => $removed->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);

        $file = $this->makeExcel([
            ['001', 'Sudah Dikunjungi', 2, ''],
            ['003', 'Baru', 1, ''],
        ]);

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import', [
                'period_id' => $period->id,
                'replace' => '1',
                'file' => new \Illuminate\Http\UploadedFile($file, 'tunggakan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.success_rows', 2);

        $this->assertDatabaseCount('arrears', 2);
        $this->assertDatabaseHas('visits', ['arrears_id' => $keptArrear->id]);
    }

    public function test_import_history_is_listable(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);
        $file = $this->makeExcel([
            ['00123456', 'Budi Santoso', 3, ''],
        ]);

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->post('/api/admin/import', [
                'period_id' => $period->id,
                'file' => new \Illuminate\Http\UploadedFile($file, 'tunggakan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
            ])
            ->assertStatus(201);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson('/api/admin/imports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', ImportLog::STATUS_SUCCESS);
    }

    private function petugas(): User
    {
        return User::create([
            'name' => 'Petugas Test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }
}
