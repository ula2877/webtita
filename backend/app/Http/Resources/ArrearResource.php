<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArrearResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'no_sambungan' => $this->customer?->no_sambungan,
            'nama' => $this->customer?->nama,
            'address' => $this->whenLoaded('customer', fn () => $this->customer?->address),
            'customer_id' => $this->customer_id,
            'jumlah_bulan_tunggakan' => $this->jumlah_bulan_tunggakan,
            'jumlah_tagihan' => $this->jumlah_tagihan,
            'period' => $this->whenLoaded('period', fn () => new PeriodResource($this->period)),
            'petugas' => $this->whenLoaded('petugas', fn () => $this->petugas ? new PetugasResource($this->petugas) : null),
            'status' => $this->status,
            'foto_bukti' => $this->foto_bukti,
            'foto_url' => $this->foto_bukti,
            'visit' => $this->whenLoaded('visit', fn () => $this->visit ? new VisitResource($this->visit) : null),
            'wilayah' => $this->when(
                $this->relationLoaded('customer') && $this->customer?->relationLoaded('wilayah'),
                fn () => $this->customer?->wilayah
                    ? (new WilayahResource($this->customer->wilayah))->resolve()
                    : null
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}