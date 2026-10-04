<?php

namespace App\Services;

use App\Models\Wilayah;
use App\Models\WilayahAlias;

class WilayahAliasService
{
    /**
     * Sinkronkan daftar alias dari config ke tabel `wilayah_aliases`.
     * Dipanggil setiap import struktur agar alias selalu tersimpan di database
     * dan pencarian wilayah pada sheet "detail tagihan" memakainya.
     */
    public function syncFromConfig(): int
    {
        $aliases = config('wilayah_aliases.aliases', []);
        if ($aliases === null || $aliases === []) {
            return 0;
        }

        $wilayah = Wilayah::query()->get(['id', 'name']);
        $wilayahByKey = [];
        foreach ($wilayah as $w) {
            $wilayahByKey[$this->normalizeWilayahNameKey($w->name)] = $w->id;
        }

        $synced = 0;
        foreach ($aliases as $alias => $wilayahName) {
            $wilayahId = $wilayahByKey[$this->normalizeWilayahNameKey((string) $wilayahName)] ?? null;
            if (! $wilayahId) {
                continue;
            }

            WilayahAlias::updateOrCreate(
                ['alias' => $this->normalizeName((string) $alias)],
                ['wilayah_id' => $wilayahId]
            );
            $synced++;
        }

        return $synced;
    }

    public function normalizeName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }

    public function normalizeWilayahNameKey(string $name): string
    {
        $lower = mb_strtolower($this->normalizeName($name));
        $clean = preg_replace('/[^a-z0-9]+/u', '', $lower) ?? $lower;

        return $this->normalizeName($clean) ?? trim($clean);
    }
}