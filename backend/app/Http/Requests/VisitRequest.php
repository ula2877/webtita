<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPetugas() ?? false;
    }

    public function rules(): array
    {
        return [
            'status_kunjungan' => ['required', Rule::in(['ada_orang', 'rumah_kosong', 'tidak_ada_orang', 'lainnya'])],
            'keterangan' => ['required_if:status_kunjungan,lainnya', 'nullable', 'string', 'max:1000'],
            'foto_bukti' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'tanggal_kunjungan' => ['required', 'date', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'status_kunjungan.required' => 'Pilih hasil kunjungan.',
            'status_kunjungan.in' => 'Hasil kunjungan tidak valid.',
            'keterangan.required_if' => 'Keterangan wajib diisi jika memilih Lainnya.',
            'foto_bukti.image' => 'File harus berupa gambar.',
            'foto_bukti.mimes' => 'Format foto harus jpg, jpeg, png, atau webp.',
            'foto_bukti.max' => 'Ukuran foto maksimal 5 MB.',
            'tanggal_kunjungan.required' => 'Tanggal kunjungan wajib diisi.',
            'tanggal_kunjungan.date' => 'Format tanggal tidak valid.',
            'tanggal_kunjungan.date_format' => 'Format tanggal harus YYYY-MM-DD.',
        ];
    }
}
