<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WilayahApiTest extends TestCase
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

    private function petugas(string $name = 'Karina'): User
    {
        return User::create([
            'name' => $name,
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    private function seedWilayah(): array
    {
        $karina = $this->petugas('Karina');
        $maulita = $this->petugas('Maulita');

        Wilayah::create(['code' => '1.1', 'name' => 'A. FAQIH', 'petugas_id' => $karina->id]);
        Wilayah::create(['code' => '1.2', 'name' => 'ANGGREK', 'petugas_id' => $maulita->id]);
        Wilayah::create(['code' => '1.3', 'name' => 'COKRO', 'petugas_id' => $karina->id]);
        Wilayah::create(['code' => '1.4', 'name' => 'TOHA', 'petugas_id' => $maulita->id]);

        return ['karina' => $karina, 'maulita' => $maulita];
    }

    public function test_admin_lists_all_wilayah_with_petugas(): void
    {
        $this->seedWilayah();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->admin()))
            ->getJson('/api/admin/wilayah')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.code', '1.1')
            ->assertJsonPath('data.0.name', 'A. FAQIH')
            ->assertJsonPath('data.0.petugas.name', 'Karina');
    }

    public function test_admin_can_search_wilayah(): void
    {
        $this->seedWilayah();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->admin()))
            ->getJson('/api/admin/wilayah?search=ANGGREK')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', '1.2');
    }

    public function test_admin_can_filter_by_petugas(): void
    {
        $seed = $this->seedWilayah();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->admin()))
            ->getJson('/api/admin/wilayah?petugas_id=' . $seed['karina']->id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_petugas_only_sees_own_wilayah(): void
    {
        $seed = $this->seedWilayah();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($seed['karina']))
            ->getJson('/api/petugas/wilayah')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'A. FAQIH')
            ->assertJsonPath('data.1.name', 'COKRO');
    }

    public function test_admin_cannot_access_petugas_only_route(): void
    {
        $this->seedWilayah();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->admin()))
            ->getJson('/api/petugas/wilayah')
            ->assertForbidden();
    }

    public function test_petugas_cannot_access_admin_wilayah_route(): void
    {
        $seed = $this->seedWilayah();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($seed['karina']))
            ->getJson('/api/admin/wilayah')
            ->assertForbidden();
    }

    public function test_user_has_many_wilayah_relationship(): void
    {
        $seed = $this->seedWilayah();

        $this->assertCount(2, $seed['karina']->wilayah()->get());
        $this->assertSame('A. FAQIH', $seed['karina']->wilayah()->orderBy('code')->first()->name);
        $this->assertSame('Karina', Wilayah::where('code', '1.1')->first()->petugas->name);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }
}