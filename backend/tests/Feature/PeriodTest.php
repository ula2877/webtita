<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_list_only_includes_months_through_current(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $twoMonthsAgo = now()->startOfMonth()->subMonthsNoOverflow(2);
        Period::create([
            'year' => $twoMonthsAgo->year,
            'month' => $twoMonthsAgo->month,
            'label' => Period::makeLabel($twoMonthsAgo->year, $twoMonthsAgo->month),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/periods')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.label', Period::makeLabel(now()->year, now()->month));
    }

    public function test_period_list_never_returns_future_months(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $token = $admin->createToken('test')->plainTextToken;

        $future = now()->startOfMonth()->addMonthsNoOverflow(2);
        $futureLabel = Period::makeLabel($future->year, $future->month);
        Period::create([
            'year' => $future->year,
            'month' => $future->month,
            'label' => $futureLabel,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/admin/periods')
            ->assertOk()
            ->assertJsonMissing(['data' => [['label' => $futureLabel]]]);
    }
}