<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateArrearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'petugas_id' => ['nullable', 'integer', 'exists:users,id'],
            'jumlah_bulan_tunggakan' => ['required', 'integer', 'min:1'],
            'jumlah_tagihan' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'petugas_id.exists' => 'Petugas tidak ditemukan.',
            'jumlah_bulan_tunggakan.required' => 'Jumlah bulan tunggakan wajib diisi.',
            'jumlah_bulan_tunggakan.integer' => 'Jumlah bulan tunggakan harus berupa angka.',
            'jumlah_bulan_tunggakan.min' => 'Jumlah bulan tunggakan minimal 1.',
            'jumlah_tagihan.integer' => 'Jumlah tagihan harus berupa angka.',
            'jumlah_tagihan.min' => 'Jumlah tagihan tidak boleh negatif.',
        ];
    }
}