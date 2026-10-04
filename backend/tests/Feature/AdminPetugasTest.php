<?php

namespace Tests\Feature;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPetugasTest extends TestCase
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

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_only_admin_can_manage_petugas(): void
    {
        $petugas = User::create([
            'name' => 'Petugas',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($petugas))
            ->postJson('/api/admin/petugas', ['name' => 'X', 'password' => 'secret'])
            ->assertStatus(403);
    }

    public function test_admin_can_create_petugas(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->admin()))
            ->postJson('/api/admin/petugas', [
                'name' => 'Budi',
                'password' => 'secret123',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.role', User::ROLE_PETUGAS);

        $this->assertDatabaseHas('users', ['name' => 'Budi', 'role' => User::ROLE_PETUGAS]);
    }

    public function test_admin_can_update_and_toggle_petugas(): void
    {
        $admin = $this->admin();
        $petugas = User::create([
            'name' => 'Budi',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->putJson("/api/admin/petugas/{$petugas->id}", ['name' => 'Budi Baru'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Baru');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->patchJson("/api/admin/petugas/{$petugas->id}/status")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_petugas_list_includes_progress(): void
    {
        $admin = $this->admin();
        $petugas = User::create([
            'name' => 'Budi',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);

        $period = Period::create(['year' => 2026, 'month' => 8, 'label' => 'Agustus 2026']);
        $customerA = Customer::create(['no_sambungan' => '001', 'nama' => 'Budi Santoso']);
        $customerB = Customer::create(['no_sambungan' => '002', 'nama' => 'Siti Aminah']);

        Arrear::create([
            'customer_id' => $customerA->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
            'petugas_id' => $petugas->id,
            'status' => Arrear::STATUS_SUDAH_DIKUNJUNGI,
        ]);

        Arrear::create([
            'customer_id' => $customerB->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 3,
            'petugas_id' => $petugas->id,
            'status' => Arrear::STATUS_BELUM_DIKUNJUNGI,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson("/api/admin/petugas?period_id={$period->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_arrears', 2)
            ->assertJsonPath('data.0.visited_arrears', 1)
            ->assertJsonPath('data.0.progress', 50);
    }
}