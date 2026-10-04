<?php

namespace Tests\Feature;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\Period;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArrearTest extends TestCase
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

    private function petugasUser(): User
    {
        return User::create([
            'name' => 'Petugas',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PETUGAS,
            'is_active' => true,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_non_admin_cannot_update_or_delete_arrear(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->petugasUser()))
            ->putJson("/api/admin/arrears/{$arrear->id}", ['jumlah_bulan_tunggakan' => 3])
            ->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->petugasUser()))
            ->deleteJson("/api/admin/arrears/{$arrear->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('arrears', ['id' => $arrear->id]);
    }

    public function test_admin_can_update_arrear(): void
    {
        $admin = $this->admin();
        $petugas = $this->petugasUser();
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
            'petugas_id' => null,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->putJson("/api/admin/arrears/{$arrear->id}", [
                'petugas_id' => $petugas->id,
                'jumlah_bulan_tunggakan' => 4,
                'jumlah_tagihan' => 209150,
            ])
            ->assertOk()
            ->assertJsonPath('data.jumlah_bulan_tunggakan', 4)
            ->assertJsonPath('data.jumlah_tagihan', 209150)
            ->assertJsonPath('data.petugas.id', $petugas->id)
            ->assertJsonPath('message', 'Data berhasil diperbarui.');

        $this->assertDatabaseHas('arrears', [
            'id' => $arrear->id,
            'petugas_id' => $petugas->id,
            'jumlah_bulan_tunggakan' => 4,
            'jumlah_tagihan' => 209150,
        ]);
    }

    public function test_update_arrear_validates_bulan_tunggakan(): void
    {
        $admin = $this->admin();
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->putJson("/api/admin/arrears/{$arrear->id}", ['jumlah_bulan_tunggakan' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jumlah_bulan_tunggakan');
    }

    public function test_admin_can_delete_only_selected_period_record(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $sept = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $okt = Period::create(['year' => 2026, 'month' => 10, 'label' => 'Oktober 2026']);

        $arrearSept = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $sept->id,
            'jumlah_bulan_tunggakan' => 2,
            'jumlah_tagihan' => 209150,
        ]);
        $arrearOkt = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $okt->id,
            'jumlah_bulan_tunggakan' => 1,
            'jumlah_tagihan' => 300000,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson("/api/admin/arrears/{$arrearOkt->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Data berhasil dihapus.');

        $this->assertDatabaseMissing('arrears', ['id' => $arrearOkt->id]);
        $this->assertDatabaseHas('arrears', ['id' => $arrearSept->id, 'jumlah_tagihan' => 209150]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'no_sambungan' => '01010609714']);
    }

    public function test_deleting_arrear_also_removes_its_visit(): void
    {
        $admin = $this->admin();
        $petugas = $this->petugasUser();
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);
        Visit::create([
            'arrears_id' => $arrear->id,
            'petugas_id' => $petugas->id,
            'status_kunjungan' => Visit::HASIL_ADA_ORANG,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson("/api/admin/arrears/{$arrear->id}")
            ->assertOk();

        $this->assertDatabaseMissing('arrears', ['id' => $arrear->id]);
        $this->assertDatabaseMissing('visits', ['arrears_id' => $arrear->id]);
    }

    public function test_admin_can_bulk_delete_selected_records(): void
    {
        $admin = $this->admin();
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customers = [
            Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']),
            Customer::create(['no_sambungan' => '01010609715', 'nama' => 'Budi']),
            Customer::create(['no_sambungan' => '01010609716', 'nama' => 'Citra']),
        ];

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = Arrear::create([
                'customer_id' => $customers[$i]->id,
                'period_id' => $period->id,
                'jumlah_bulan_tunggakan' => 2,
            ])->id;
        }

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson('/api/admin/arrears/bulk', ['ids' => [$ids[0], $ids[2]]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 2)
            ->assertJsonPath('message', '2 data tunggakan berhasil dihapus.');

        $this->assertDatabaseMissing('arrears', ['id' => $ids[0]]);
        $this->assertDatabaseMissing('arrears', ['id' => $ids[2]]);
        $this->assertDatabaseHas('arrears', ['id' => $ids[1]]);
    }

    public function test_bulk_delete_preserves_other_periods_and_customers(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $sept = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $okt = Period::create(['year' => 2026, 'month' => 10, 'label' => 'Oktober 2026']);

        $arrearSept = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $sept->id,
            'jumlah_bulan_tunggakan' => 2,
            'jumlah_tagihan' => 209150,
        ]);
        $arrearOkt = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $okt->id,
            'jumlah_bulan_tunggakan' => 1,
            'jumlah_tagihan' => 300000,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson('/api/admin/arrears/bulk', ['ids' => [$arrearOkt->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1);

        $this->assertDatabaseMissing('arrears', ['id' => $arrearOkt->id]);
        $this->assertDatabaseHas('arrears', ['id' => $arrearSept->id, 'jumlah_tagihan' => 209150]);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'no_sambungan' => '01010609714']);
    }

    public function test_bulk_delete_removes_visits_of_deleted_records(): void
    {
        $admin = $this->admin();
        $petugas = $this->petugasUser();
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);

        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);
        Visit::create([
            'arrears_id' => $arrear->id,
            'petugas_id' => $petugas->id,
            'status_kunjungan' => Visit::HASIL_ADA_ORANG,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson('/api/admin/arrears/bulk', ['ids' => [$arrear->id]])
            ->assertOk()
            ->assertJsonPath('deleted_count', 1);

        $this->assertDatabaseMissing('arrears', ['id' => $arrear->id]);
        $this->assertDatabaseMissing('visits', ['arrears_id' => $arrear->id]);
    }

    public function test_bulk_delete_validates_ids(): void
    {
        $admin = $this->admin();
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson('/api/admin/arrears/bulk', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson('/api/admin/arrears/bulk', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->deleteJson('/api/admin/arrears/bulk', ['ids' => [$arrear->id, 999999]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids.1');

        $this->assertDatabaseHas('arrears', ['id' => $arrear->id]);
    }

    public function test_non_admin_cannot_bulk_delete(): void
    {
        $period = Period::create(['year' => 2026, 'month' => 9, 'label' => 'September 2026']);
        $customer = Customer::create(['no_sambungan' => '01010609714', 'nama' => 'Ahmad']);
        $arrear = Arrear::create([
            'customer_id' => $customer->id,
            'period_id' => $period->id,
            'jumlah_bulan_tunggakan' => 2,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($this->petugasUser()))
            ->deleteJson('/api/admin/arrears/bulk', ['ids' => [$arrear->id]])
            ->assertStatus(403);

        $this->assertDatabaseHas('arrears', ['id' => $arrear->id]);
    }
}