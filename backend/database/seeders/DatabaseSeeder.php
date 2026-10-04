<?php

namespace Database\Seeders;

use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demoPassword = bcrypt('password');

        User::updateOrCreate(
            ['name' => 'Iftitah Aulia Ahsani, S.T.'],
            [
                'password' => $demoPassword,
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]
        );

        // ====================================================================
        // AKUN PETUGAS — DEMO / SEMENTARA
        // Password semua akun demo: "password" (ganti sebelum production).
        // ====================================================================
        $petugas = [
            'Irnita Agustin Putri, S.E.',
            'Karina Impiana Sari, S.H.',
            'Maulita Utami',
            'Oktavia Siswi Purnamasari',
            'Zulfikur Aini Hisbulwaton',
        ];

        foreach ($petugas as $name) {
            User::updateOrCreate(
                ['name' => $name],
                [
                    'password' => $demoPassword,
                    'role' => User::ROLE_PETUGAS,
                    'is_active' => true,
                ]
            );
        }

        $periodCursor = now()->startOfMonth()->subMonth();
        $periodEnd = now()->startOfMonth();

        while ($periodCursor->lte($periodEnd)) {
            Period::firstOrCreate(
                ['year' => $periodCursor->year, 'month' => $periodCursor->month],
                ['label' => Period::makeLabel($periodCursor->year, $periodCursor->month)]
            );
            $periodCursor->addMonth();
        }
    }
}
