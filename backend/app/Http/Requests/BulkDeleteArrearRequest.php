<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteArrearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:arrears,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu data tunggakan.',
            'ids.array' => 'Format data yang dipilih tidak valid.',
            'ids.min' => 'Pilih minimal satu data tunggakan.',
            'ids.*.integer' => 'Format data yang dipilih tidak valid.',
            'ids.*.exists' => 'Beberapa data tunggakan tidak ditemukan.',
        ];
    }
}