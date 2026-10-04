<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagihanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'period' => $this->whenLoaded('period', fn () => new PeriodResource($this->period)),
            'no_sambungan' => $this->whenLoaded('customer', fn () => $this->customer?->no_sambungan),
            'nama' => $this->whenLoaded('customer', fn () => $this->customer?->nama),
            'address' => $this->whenLoaded('customer', fn () => $this->customer?->address),
            'jumlah_bulan_tunggakan' => $this->jumlah_bulan_tunggakan,
            'jumlah_tagihan' => $this->jumlah_tagihan,
            'status' => $this->status,
            'wilayah' => $this->when(
                $this->relationLoaded('customer') && $this->customer?->relationLoaded('wilayah'),
                fn () => $this->customer?->wilayah
                    ? (new WilayahResource($this->customer->wilayah))->resolve()
                    : null
            ),
            'petugas' => $this->whenLoaded('petugas', fn () => $this->petugas ? (new PetugasResource($this->petugas))->resolve() : null),
        ];
    }
}