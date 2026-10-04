<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PetugasImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File Excel wajib diunggah.',
            'file.file' => 'Upload harus berupa file.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ];
    }
}