<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'no_sambungan' => $this->no_sambungan,
            'nama' => $this->nama,
            'address' => $this->address,
            'wilayah' => $this->whenLoaded('wilayah', fn () => $this->wilayah ? (new WilayahResource($this->wilayah))->resolve() : null),
            'tagihan' => TagihanResource::collection($this->whenLoaded('tagihan')),
        ];
    }
}