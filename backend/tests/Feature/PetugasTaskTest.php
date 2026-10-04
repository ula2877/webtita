<?php

namespace Tests\Feature;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\Period;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PetugasTaskTest extends TestCase
{
    use RefreshDatabase;

    private function petugas(): User
    {
        return User::create([
            'name' => 'Petugas Test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    private function otherPetugas(): User
    {
        return User::create([
            'name' => 'Petugas Lain',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function setupArrear(User $petugas, string $noSambungan = '00123456', string $nama = 'Budi'): Arrear
    {
        $period = Period::firstOrCreate(
            ['year' => 2026, 'month' => 8],
            ['label' => 'Agustus 2026']
        );
        $customer = Customer::create(['no_sambungan' => $noSambungan, 'nama' => $nama]);

        return Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 3,
            'petugas_id' => $petugas->id,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_petugas_only_sees_own_tasks(): void
    {
        $petugas = $this->petugas();
        $other = $this->otherPetugas();

        $this->setupArrear($petugas, '001', 'Punya Saya');
        $this->setupArrear($other, '002', 'Punya Lain');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->getJson('/api/petugas/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.no_sambungan', '001');
    }

    public function test_petugas_cannot_view_others_task(): void
    {
        $petugas = $this->petugas();
        $other = $this->otherPetugas();
        $arrear = $this->setupArrear($other, '001', 'Punya Lain');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->getJson("/api/petugas/tasks/{$arrear->id}")
            ->assertStatus(403);
    }

    public function test_petugas_can_submit_visit_with_photo(): void
    {
        Storage::fake('public');

        $petugas = $this->petugas();
        $arrear = $this->setupArrear($petugas);

        $photo = UploadedFile::fake()->image('bukti.jpg', 320, 240);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->post("/api/petugas/tasks/{$arrear->id}/visit", [
                'status_kunjungan' => 'ada_orang',
                'keterangan' => 'Surat diterima langsung.',
                'foto_bukti' => $photo,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Hasil kunjungan berhasil disimpan.')
            ->assertJsonPath('data.status_kunjungan', 'ada_orang');

        $this->assertDatabaseHas('visits', [
            'arrears_id' => $arrear->id,
            'petugas_id' => $petugas->id,
        ]);

        $this->assertDatabaseHas('arrears', [
            'id' => $arrear->id,
            'status' => Arrear::STATUS_SUDAH_DIKUNJUNGI,
        ]);

        Storage::disk('public')->assertExists($response->json('data.foto_bukti'));
    }

    public function test_visit_requires_keterangan_when_lainnya(): void
    {
        $petugas = $this->petugas();
        $arrear = $this->setupArrear($petugas);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->postJson("/api/petugas/tasks/{$arrear->id}/visit", [
                'status_kunjungan' => 'lainnya',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('keterangan');
    }

    public function test_petugas_cannot_submit_visit_on_others_task(): void
    {
        $petugas = $this->petugas();
        $other = $this->otherPetugas();
        $arrear = $this->setupArrear($other);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->post("/api/petugas/tasks/{$arrear->id}/visit", [
                'status_kunjungan' => 'ada_orang',
            ])
            ->assertStatus(403);
    }

    public function test_dashboard_returns_stats(): void
    {
        $petugas = $this->petugas();
        $this->setupArrear($petugas, '001');
        $this->setupArrear($petugas, '002');

        $arrear = Arrear::whereHas('customer', fn ($q) => $q->where('no_sambungan', '001'))->first();
        $arrear->visit()->create([
            'petugas_id' => $petugas->id,
            'status_kunjungan' => 'rumah_kosong',
            'visited_at' => now(),
        ]);
        $arrear->update(['status' => Arrear::STATUS_SUDAH_DIKUNJUNGI]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->getJson('/api/petugas/dashboard')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('visited', 1)
            ->assertJsonPath('unvisited', 1)
            ->assertJsonPath('progress', 50);
    }

    public function test_admin_can_view_arrear_detail_and_photo(): void
    {
        Storage::fake('public');

        $petugas = $this->petugas();
        $arrear = $this->setupArrear($petugas);

        $photo = UploadedFile::fake()->image('bukti.jpg', 320, 240);
        $path = $photo->store('photos', 'public');
        $photoPath = basename($path);

        $arrear->visit()->create([
            'petugas_id' => $petugas->id,
            'status_kunjungan' => 'ada_orang',
            'keterangan' => 'Diterima.',
            'foto_bukti' => $path,
            'visited_at' => now(),
        ]);
        $arrear->update(['status' => Arrear::STATUS_SUDAH_DIKUNJUNGI]);

        $admin = $this->admin();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson("/api/admin/arrears/{$arrear->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'sudah_dikunjungi')
            ->assertJsonPath('data.visit.foto_bukti', $path);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->get("/api/photos/{$photoPath}")
            ->assertOk();
    }

    public function test_petugas_cannot_access_others_photo(): void
    {
        Storage::fake('public');

        $petugas = $this->petugas();
        $other = $this->otherPetugas();
        $arrear = $this->setupArrear($petugas);

        $photo = UploadedFile::fake()->image('bukti.jpg', 320, 240);
        $path = $photo->store('photos', 'public');

        $arrear->visit()->create([
            'petugas_id' => $petugas->id,
            'status_kunjungan' => 'ada_orang',
            'foto_bukti' => $path,
            'visited_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($other))
            ->get('/api/photos/' . basename($path))
            ->assertStatus(403);
    }
}
