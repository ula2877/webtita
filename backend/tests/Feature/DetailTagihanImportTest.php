<?php

namespace Tests\Feature;

use App\Models\Arrear;
use App\Models\Customer;
use App\Models\ImportLog;
use App\Models\Period;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DetailTagihanImportTest extends TestCase
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

    /**
     * $ketua: list of petugas names.
     * $rekap: list of ['NO','ALAMAT','ID','TIM PENAGIH'] rows.
     * $groups: list of group maps OR map sheet title => list of group maps:
     *   ['wilayah' => 'RE MARTADINATA', 'date' => '2026-01-15', 'customers' => [
     *      ['01010609714', 'BUDI', 'JL MELATI NO 1', 3, '209,150'],
     *   ]]
     */
    private function makeWorkbook(array $ketua, array $rekap, array $groups): string
    {
        $spreadsheet = new Spreadsheet();

        $ketuaSheet = $spreadsheet->getActiveSheet();
        $ketuaSheet->setTitle('ketua kelompok');
        $ketuaSheet->fromArray(['no', 'nama'], null, 'A1');
        foreach ($ketua as $i => $name) {
            $ketuaSheet->setCellValue('A' . ($i + 2), $i + 1);
            $ketuaSheet->setCellValue('B' . ($i + 2), $name);
        }

        $rekapSheet = $spreadsheet->createSheet();
        $rekapSheet->setTitle('rekap wilayah');
        $rekapSheet->fromArray(['NO', 'ALAMAT', 'ID', 'TIM PENAGIH'], null, 'A1');
        foreach ($rekap as $i => $row) {
            $rekapSheet->fromArray($row, null, 'A' . ($i + 2));
        }

        $firstKey = array_key_first($groups);
        $sheetMap = is_string($firstKey) ? $groups : ['detail tagihan' => $groups];

        foreach ($sheetMap as $title => $groupsForSheet) {
            $detail = $spreadsheet->createSheet();
            $detail->setTitle($title);
            $row = 1;
            foreach ($groupsForSheet as $group) {
                $detail->setCellValue('A' . $row, count($group['customers']));
                if (! empty($group['date_string'])) {
                    $detail->getCell('B' . $row)->setValueExplicit($group['date'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $detail->setCellValue('B' . $row, Date::PHPToExcel(new \DateTime($group['date'])));
                    $detail->getStyle('B' . $row)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                }
                $detail->setCellValue('C' . $row, $group['wilayah']);
                $detail->setCellValue('F' . $row, '1,000,000');

                $row++;
                $detail->fromArray(['NO', 'NO.SAMBUNGAN', 'NAMA', 'ALAMAT', 'BLN', 'TAGIHAN'], null, 'A' . $row);
                $row++;
                foreach ($group['customers'] as $i => $c) {
                    $detail->fromArray([$i + 1, $c[0], $c[1], $c[2], $c[3], $c[4]], null, 'A' . $row);
                    $detail->getCell('B' . $row)->setValueExplicit((string) $c[0], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $row++;
                }
            }
        }

        $path = sys_get_temp_dir() . '/detail_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function file(string $path): UploadedFile
    {
        return new UploadedFile($path, 'data.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function import(string $path, User $user)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->post('/api/admin/import/wilayah', ['file' => $this->file($path)]);
    }

    public function test_import_creates_pelanggan_and_tagihan_with_petugas(): void
    {
        $petugas = $this->petugas('Karina Impiana Sari, S.H.');
        $file = $this->makeWorkbook(
            ['Karina Impiana Sari, S.H.'],
            [['1', 'RE MARTADINATA', '1.1', 'Karina Impiana Sari, S.H.']],
            [[
                'wilayah' => 'RE MARTADINATA',
                'date' => '2026-01-15',
                'customers' => [
                    ['01010609714', 'BUDI SANTOSO', 'JL MELATI NO 1', 3, '209,150'],
                    ['01010609715', 'SITI AMINAH', 'JL MELATI NO 2', 6, 285000],
                ],
            ]],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.total', 2)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 2)
            ->assertJsonPath('detail_tagihan.pelanggan_updated', 0)
            ->assertJsonPath('detail_tagihan.tagihan_created', 2)
            ->assertJsonPath('detail_tagihan.invalid', 0);

        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('arrears', 2);

        $wilayah = Wilayah::where('code', '1.1')->first();
        $bud = Customer::where('no_sambungan', '01010609714')->first();
        $this->assertSame($wilayah->id, $bud->wilayah_id);
        $this->assertSame('BUDI SANTOSO', $bud->nama);
        $this->assertSame('JL MELATI NO 1', $bud->address);

        $arrear = Arrear::where('customer_id', $bud->id)->first();
        $this->assertSame($petugas->id, $arrear->petugas_id);
        $this->assertSame(3, $arrear->jumlah_bulan_tunggakan);
        $this->assertSame(209150, $arrear->jumlah_tagihan);

        $period = Period::where('year', 2026)->where('month', 1)->first();
        $this->assertNotNull($period);
        $this->assertSame('Januari 2026', $period->label);
        $this->assertSame($period->id, $arrear->period_id);

        $log = ImportLog::where('sheet_name', 'detail tagihan')->first();
        $this->assertNotNull($log);
        $this->assertSame(ImportLog::STATUS_SUCCESS, $log->status);
        $this->assertSame(2, $log->success_rows);
        $this->assertSame(0, $log->failed_rows);
    }

    public function test_reimport_upserts_without_duplicates(): void
    {
        $file = $this->makeWorkbook(
            ['Karina Impiana Sari, S.H.'],
            [['1', 'TOHA', '1.2', 'Karina Impiana Sari, S.H.']],
            [[
                'wilayah' => 'TOHA',
                'date' => '2026-01-15',
                'customers' => [
                    ['01010609714', 'BUDI', 'JL MELATI NO 1', 3, 209150],
                    ['01010609715', 'SITI', 'JL MELATI NO 2', 6, 285000],
                ],
            ]],
        );

        $this->import($file, $this->admin())->assertStatus(201);

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 0)
            ->assertJsonPath('detail_tagihan.pelanggan_updated', 2)
            ->assertJsonPath('detail_tagihan.tagihan_created', 0)
            ->assertJsonPath('detail_tagihan.tagihan_updated', 2);

        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('arrears', 2);
    }

    public function test_new_period_creates_arrear_and_preserves_old(): void
    {
        $file01 = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [[
                'wilayah' => 'TOHA',
                'date' => '2026-01-15',
                'customers' => [['01010609714', 'BUDI', 'JL MELATI', 3, 209150]],
            ]],
        );
        $file02 = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [[
                'wilayah' => 'TOHA',
                'date' => '2026-02-15',
                'customers' => [['01010609714', 'BUDI', 'JL MELATI', 4, 300000]],
            ]],
        );

        $this->import($file01, $this->admin())->assertStatus(201);
        $this->import($file02, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 0)
            ->assertJsonPath('detail_tagihan.tagihan_created', 1)
            ->assertJsonPath('detail_tagihan.tagihan_updated', 0);

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('arrears', 2);

        $bud = Customer::where('no_sambungan', '01010609714')->first();
        $old = Arrear::firstWhere(['customer_id' => $bud->id, 'period_id' => Period::where('month', 1)->value('id')]);
        $this->assertSame(3, $old->jumlah_bulan_tunggakan);
    }

    public function test_alias_from_config_matches_wilayah(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'MY. SUNGKONO', '1.3', 'Karina']],
            [[
                'wilayah' => 'MAY SUNGKONO',
                'date' => '2026-01-15',
                'customers' => [['01010609714', 'BUDI', 'JL SUNGKONO', 2, 150000]],
            ]],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.invalid', 0)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 1);

        $my = Wilayah::where('name', 'MY. SUNGKONO')->first();
        $this->assertNotNull($my);
        $this->assertDatabaseHas('wilayah_aliases', ['alias' => 'MAY SUNGKONO', 'wilayah_id' => $my->id]);
        $this->assertSame($my->id, Customer::first()->wilayah_id);
    }

    public function test_normalized_match_without_punctuation(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'JA. SUPRAPTO', '1.4', 'Karina']],
            [[
                'wilayah' => 'J.A SUPRAPTO',
                'date' => '2026-01-15',
                'customers' => [['01010609714', 'BUDI', 'JL SUPRAPTO', 2, 150000]],
            ]],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 1)
            ->assertJsonPath('detail_tagihan.invalid', 0);

        $this->assertSame(Wilayah::where('name', 'JA. SUPRAPTO')->value('id'), Customer::first()->wilayah_id);
    }

    public function test_unknown_wilayah_skips_customers_and_is_reported(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [[
                'wilayah' => 'WILAYAH TIDAK ADA',
                'date' => '2026-01-15',
                'customers' => [
                    ['01010609714', 'BUDI', 'JL A', 2, 150000],
                    ['01010609715', 'SITI', 'JL B', 2, 150000],
                ],
            ]],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.total', 2)
            ->assertJsonPath('detail_tagihan.invalid', 2)
            ->assertJsonPath('detail_tagihan.wilayah_not_found.0', 'WILAYAH TIDAK ADA');

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('arrears', 0);
    }

    public function test_row_error_does_not_stop_other_customers(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [[
                'wilayah' => 'TOHA',
                'date' => '2026-01-15',
                'customers' => [
                    ['01010609714', 'BUDI', 'JL A', 2, 150000],
                    ['01010609715', 'RUMAH TANPA NAMA', 'JL B', 'BUKAN ANGKA', 150000],
                    ['01010609716', 'SITI', 'JL C', 3, 250000],
                ],
            ]],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.total', 3)
            ->assertJsonPath('detail_tagihan.invalid', 1)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 2);

        $this->assertDatabaseCount('customers', 2);
    }

    public function test_leading_zero_no_sambungan_is_preserved(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [[
                'wilayah' => 'TOHA',
                'date' => '2026-01-15',
                'customers' => [['01010609714', 'BUDI', 'JL A', 2, 150000]],
            ]],
        );

        $this->import($file, $this->admin())->assertStatus(201);
        $this->assertDatabaseHas('customers', ['no_sambungan' => '01010609714']);

        // Reimport dengan file sama tetap match (string utuh, bukan angka).
        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 0)
            ->assertJsonPath('detail_tagihan.pelanggan_updated', 1);
    }

    public function test_missing_detail_sheet_returns_unprocessable(): void
    {
        $spreadsheet = new Spreadsheet();
        $ketua = $spreadsheet->getActiveSheet()->setTitle('ketua kelompok');
        $ketua->fromArray(['no', 'nama'], null, 'A1');
        $ketua->fromArray(['1', 'Karina'], null, 'A2');

        $rekap = $spreadsheet->createSheet()->setTitle('rekap wilayah');
        $rekap->fromArray(['NO', 'ALAMAT', 'ID', 'TIM PENAGIH'], null, 'A1');
        $rekap->fromArray(['1', 'TOHA', '1.2', 'Karina'], null, 'A2');

        $path = sys_get_temp_dir() . '/detail_import_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token($this->admin()),
            'Accept' => 'application/json',
        ])
            ->post('/api/admin/import/wilayah', ['file' => $this->file($path)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_text_date_group_start_is_recognized(): void
    {
        // Excel nyata menyimpan beberapa tanggal kelompok sebagai TEKS ('04/09/2026'),
        // bukan sel berformat tanggal. Kelompok ini harus tetap dikenali sebagai
        // awal kelompok dan pelanggannya dicocokkan lewat alias 'MANGUN'.
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'MANGUN PERSADA', '1.9', 'Karina']],
            [[
                'wilayah' => 'MANGUN',
                'date' => '15/01/2026',
                'date_string' => true,
                'customers' => [
                    ['01010609714', 'H. HANAFI', 'MANGUN PERSADA C - 3', 3, 150000],
                    ['01010609715', 'M. USMAN EFENDI', 'MANGUN PERSADA B - 3', 3, 150000],
                ],
            ]],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.total', 2)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 2)
            ->assertJsonPath('detail_tagihan.invalid', 0);

        $mangun = Wilayah::where('name', 'MANGUN PERSADA')->first();
        $this->assertNotNull($mangun);
        $this->assertDatabaseHas('wilayah_aliases', ['alias' => 'MANGUN', 'wilayah_id' => $mangun->id]);
        $this->assertDatabaseHas('customers', ['no_sambungan' => '01010609714', 'wilayah_id' => $mangun->id]);
    }

    public function test_admin_arrears_index_lists_and_filters(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [[
                'wilayah' => 'TOHA',
                'date' => '2026-01-15',
                'customers' => [['01010609714', 'BUDI', 'JL A', 2, 150000]],
            ]],
        );
        $this->import($file, $this->admin())->assertStatus(201);

        $admin = $this->admin();
        $period = Period::where('month', 1)->first();
        $wilayah = Wilayah::where('name', 'TOHA')->first();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson('/api/admin/arrears')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.no_sambungan', '01010609714')
            ->assertJsonPath('data.0.jumlah_tagihan', 150000)
            ->assertJsonPath('data.0.wilayah.name', 'TOHA')
            ->assertJsonPath('data.0.period.label', 'Januari 2026');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson('/api/admin/arrears?search=BUDI')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson('/api/admin/arrears?search=TIDAK ADA')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson('/api/admin/arrears?period_id=' . $period->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeader('Authorization', 'Bearer ' . $this->token($admin))
            ->getJson('/api/admin/arrears?wilayah_id=' . $wilayah->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.wilayah.id', $wilayah->id);
    }

    public function test_petugas_pelanggan_endpoint_is_scoped_and_filters_period(): void
    {
        $file = $this->makeWorkbook(
            ['Karina', 'Maulita'],
            [
                ['1', 'TOHA', '1.2', 'Karina'],
                ['2', 'A. FAQIH', '1.1', 'Maulita'],
            ],
            [
                [
                    'wilayah' => 'TOHA',
                    'date' => '2026-01-15',
                    'customers' => [['01010609714', 'BUDI', 'JL A', 2, 150000]],
                ],
                [
                    'wilayah' => 'A. FAQIH',
                    'date' => '2026-01-15',
                    'customers' => [['01010609716', 'SITI', 'JL B', 3, 250000]],
                ],
            ],
        );
        $this->import($file, $this->admin())->assertStatus(201);

        $karina = User::where('name', 'Karina')->first();
        $maulita = User::where('name', 'Maulita')->first();

        $this->actingAs($karina)
            ->getJson('/api/petugas/pelanggan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.no_sambungan', '01010609714')
            ->assertJsonPath('data.0.wilayah.name', 'TOHA')
            ->assertJsonCount(1, 'data.0.tagihan');

        $this->actingAs($karina)
            ->getJson('/api/petugas/pelanggan?search=01010609716')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Periode lama (tidak ada tagihan yang cocok) → data kosong tapi list tetap.
        $period = Period::where('year', 2025)->first();
        if (! $period) {
            $period = Period::create(['year' => 2025, 'month' => 1, 'label' => 'Januari 2025']);
        }
        $this->actingAs($karina)
            ->getJson('/api/petugas/pelanggan?period_id=' . $period->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(0, 'data.0.tagihan');

        // Petugas lain tidak boleh melihat pelanggan Karina.
        $this->actingAs($maulita)
            ->getJson('/api/petugas/pelanggan?search=BUDI')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_petugas_dashboard_returns_wilayah_stats(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [[
                'wilayah' => 'TOHA',
                'date' => '2026-01-15',
                'customers' => [
                    ['01010609714', 'BUDI', 'JL A', 2, 150000],
                    ['01010609715', 'SITI', 'JL B', 3, 250000],
                ],
            ]],
        );
        $this->import($file, $this->admin())->assertStatus(201);

        $karina = User::where('name', 'Karina')->first();

        $this->actingAs($karina)
            ->getJson('/api/petugas/dashboard')
            ->assertOk()
            ->assertJsonPath('total_pelanggan', 2)
            ->assertJsonPath('total_nominal_tagihan', 400000)
            ->assertJsonCount(1, 'wilayah_stats')
            ->assertJsonPath('wilayah_stats.0.name', 'TOHA')
            ->assertJsonPath('wilayah_stats.0.customer_count', 2)
            ->assertJsonPath('wilayah_stats.0.total_tagihan', 400000);
    }

    public function test_multiple_detail_sheets_are_imported_aggregated(): void
    {
        $file = $this->makeWorkbook(
            ['Karina', 'Maulita'],
            [
                ['1', 'TOHA', '1.2', 'Karina'],
                ['2', 'A. FAQIH', '1.1', 'Maulita'],
            ],
            [
                'detail tagihan 1' => [[
                    'wilayah' => 'TOHA',
                    'date' => '2026-01-15',
                    'customers' => [
                        ['01010609714', 'BUDI', 'JL A', 2, 150000],
                        ['01010609715', 'SITI', 'JL B', 3, 250000],
                    ],
                ]],
                'detail tagihan 2' => [[
                    'wilayah' => 'A. FAQIH',
                    'date' => '2026-02-15',
                    'customers' => [
                        ['01010609716', 'AMIN', 'JL C', 4, 300000],
                    ],
                ]],
            ],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.sheet_count', 2)
            ->assertJsonPath('detail_tagihan.sheets.0.sheet_name', 'detail tagihan 1')
            ->assertJsonPath('detail_tagihan.sheets.0.total', 2)
            ->assertJsonPath('detail_tagihan.sheets.1.sheet_name', 'detail tagihan 2')
            ->assertJsonPath('detail_tagihan.sheets.1.total', 1)
            ->assertJsonPath('detail_tagihan.total', 3)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 3)
            ->assertJsonPath('detail_tagihan.invalid', 0);

        $this->assertDatabaseCount('customers', 3);
        $this->assertDatabaseCount('arrears', 3);

        $this->assertDatabaseHas('import_logs', ['sheet_name' => 'detail tagihan 1']);
        $this->assertDatabaseHas('import_logs', ['sheet_name' => 'detail tagihan 2']);

        $jan = Period::where('year', 2026)->where('month', 1)->first();
        $feb = Period::where('year', 2026)->where('month', 2)->first();
        $this->assertNotNull($jan);
        $this->assertNotNull($feb);

        $karina = User::where('name', 'Karina')->first();
        $maulita = User::where('name', 'Maulita')->first();

        $bud = Arrear::whereHas('customer', fn ($q) => $q->where('no_sambungan', '01010609714'))->first();
        $amin = Arrear::whereHas('customer', fn ($q) => $q->where('no_sambungan', '01010609716'))->first();
        $this->assertSame($karina->id, $bud->petugas_id);
        $this->assertSame($jan->id, $bud->period_id);
        $this->assertSame($maulita->id, $amin->petugas_id);
        $this->assertSame($feb->id, $amin->period_id);
    }

    public function test_multiple_detail_sheets_share_duplicate_detection(): void
    {
        $file = $this->makeWorkbook(
            ['Karina'],
            [['1', 'TOHA', '1.2', 'Karina']],
            [
                'detail tagihan 1' => [[
                    'wilayah' => 'TOHA',
                    'date' => '2026-01-15',
                    'customers' => [['01010609714', 'BUDI', 'JL A', 2, 150000]],
                ]],
                'detail tagihan 2' => [[
                    'wilayah' => 'TOHA',
                    'date' => '2026-01-15',
                    'customers' => [['01010609714', 'BUDI', 'JL A', 2, 150000]],
                ]],
            ],
        );

        $this->import($file, $this->admin())
            ->assertStatus(201)
            ->assertJsonPath('detail_tagihan.total', 2)
            ->assertJsonPath('detail_tagihan.invalid', 1)
            ->assertJsonPath('detail_tagihan.pelanggan_created', 1);

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('arrears', 1);

        $sheet1 = ImportLog::where('sheet_name', 'detail tagihan 1')->first();
        $sheet2 = ImportLog::where('sheet_name', 'detail tagihan 2')->first();
        $this->assertSame(0, $sheet1->failed_rows);
        $this->assertSame(1, $sheet2->failed_rows);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }
}