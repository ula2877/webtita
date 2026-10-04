<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PeriodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'label' => $this->label,
        ];
    }
}
