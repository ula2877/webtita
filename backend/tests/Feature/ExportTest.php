<?php

namespace Tests\Feature;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\Period;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportTest extends TestCase
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

    public function test_admin_can_export_excel(): void
    {
        $admin = $this->admin();
        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);
        $customer = Customer::create(['no_sambungan' => '00123456', 'nama' => 'Budi Santoso']);
        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 3,
        ]);

        $arrear->visit()->create([
            'petugas_id' => $admin->id,
            'status_kunjungan' => Visit::HASIL_ADA_ORANG,
            'keterangan' => 'Diterima',
            'visited_at' => now(),
        ]);

        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/admin/export?period_id={$period->id}");

        $response->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('hasil_penagihan', $response->headers->get('Content-Disposition'));

        $file = $response->baseResponse->getFile();
        $content = $file ? file_get_contents($file->getPathname()) : $response->streamedContent();
        $this->assertNotEmpty($content);

        $path = sys_get_temp_dir() . '/export_test.xlsx';
        file_put_contents($path, $content);
        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $this->assertSame('No Sambungan', $rows[0][1]);
        $this->assertSame('00123456', $rows[1][1]);
        $this->assertSame('Budi Santoso', $rows[1][2]);
        $this->assertSame('Ada Orang', $rows[1][6]);
        unlink($path);
    }

    public function test_petugas_cannot_export(): void
    {
        $petugas = User::create([
            'name' => 'Petugas',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $petugas->createToken('test')->plainTextToken)
            ->getJson('/api/admin/export')
            ->assertStatus(403);
    }
}
