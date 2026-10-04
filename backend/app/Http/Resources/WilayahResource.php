<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WilayahResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'petugas' => $this->whenLoaded('petugas', fn () => $this->petugas ? (new UserResource($this->petugas))->resolve() : null),
        ];
    }
}