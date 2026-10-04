<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use HasFactory;

    public const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    protected $fillable = [
        'year',
        'month',
        'label',
    ];

    public function arrears(): HasMany
    {
        return $this->hasMany(Arrear::class);
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    public static function makeLabel(int $year, int $month): string
    {
        return self::MONTH_NAMES[$month] . ' ' . $year;
    }

    public static function ensureThroughCurrentMonth(): void
    {
        $end = now()->startOfMonth();
        $earliest = self::orderBy('year')->orderBy('month')->first();

        $cursor = $earliest
            ? now()->setDate((int) $earliest->year, (int) $earliest->month, 1)->startOfMonth()
            : now()->startOfMonth();

        while ($cursor->lte($end)) {
            self::firstOrCreate(
                ['year' => $cursor->year, 'month' => $cursor->month],
                ['label' => self::makeLabel($cursor->year, $cursor->month)]
            );
            $cursor->addMonth();
        }
    }
}
