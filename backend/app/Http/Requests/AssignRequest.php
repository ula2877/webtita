<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'arrear_ids' => ['required', 'array', 'min:1'],
            'arrear_ids.*' => ['integer', 'exists:arrears,id'],
            'petugas_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'arrear_ids.required' => 'Pilih minimal satu data tunggakan.',
            'arrear_ids.min' => 'Pilih minimal satu data tunggakan.',
            'petugas_id.required' => 'Petugas wajib dipilih.',
        ];
    }
}
