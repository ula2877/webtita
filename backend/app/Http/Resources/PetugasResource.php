<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PetugasResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'is_active' => (bool) $this->is_active,
            'total_arrears' => $this->when(isset($this->total_arrears), $this->total_arrears),
            'visited_arrears' => $this->when(isset($this->visited_arrears), $this->visited_arrears),
            'progress' => $this->when(isset($this->progress), $this->progress),
        ];
    }
}
