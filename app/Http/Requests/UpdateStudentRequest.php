<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest{
    public function authorize() {
        return true;
    }

    public function rules(){
        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'nis' => ['sometimes', 'string', Rule::unique('students', 'nis')->ignore($this->route('id'))],
            'sekolah' => 'sometimes|string|max:255',
            'alamat' => 'sometimes|string|max:500',
            'no_hp' => 'sometimes|string|max:15',
        ];
    }

    public function messages() {
        return [
            'name.string' => 'Nama siswa harus berupa teks',
            'name.max' => 'Nama siswa maksimal 255 karakter',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'nis.string' => 'NIS harus berupa teks',
            'nis.unique' => 'NIS sudah terdaftar',
            'sekolah.string' => 'Sekolah harus berupa teks',
            'sekolah.max' => 'Sekolah maksimal 255 karakter',
            'alamat.string' => 'Alamat harus berupa teks',
            'alamat.max' => 'Alamat maksimal 500 karakter',
            'no_hp.string' => 'Nomor HP harus berupa teks',
            'no_hp.max' => 'Nomor HP maksimal 15 karakter',
        ];
    }
}
