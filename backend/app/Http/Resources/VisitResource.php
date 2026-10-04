<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'arrears_id' => $this->arrears_id,
            'petugas_id' => $this->petugas_id,
            'petugas_name' => $this->whenLoaded('petugas', fn () => $this->petugas?->name),
            'status_kunjungan' => $this->status_kunjungan,
            'keterangan' => $this->keterangan,
            'foto_bukti' => $this->foto_bukti,
            'foto_url' => $this->foto_bukti,
            'visited_at' => $this->visited_at?->toIso8601String(),
        ];
    }
}
