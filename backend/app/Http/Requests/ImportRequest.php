<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'period_id.required' => 'Periode wajib dipilih.',
            'period_id.exists' => 'Periode tidak valid.',
            'file.required' => 'File Excel wajib diupload.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ];
    }
}
