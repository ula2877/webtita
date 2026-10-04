<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ImportLogResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'period' => $this->whenLoaded('period', fn () => new PeriodResource($this->period)),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'file_name' => $this->file_name,
            'sheet_name' => $this->sheet_name,
            'total_rows' => $this->total_rows,
            'success_rows' => $this->success_rows,
            'failed_rows' => $this->failed_rows,
            'created_users' => $this->created_users,
            'existing_users' => $this->existing_users,
            'invalid_rows' => $this->invalid_rows,
            'status' => $this->status,
            'error_details' => $this->error_details,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
