<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest {
    public function authorize(){
        return true;
    }

    public function rules(){
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8',
            'nis' => ['required', 'string', Rule::unique('students', 'nis')],
            'sekolah' => 'required|string|max:255',
            'alamat' => 'required|string|max:500',
            'no_hp' => 'required|string|max:15',
        ];
    }

    public function messages(){
        return [
            'name.required' => 'Nama siswa wajib diisi',
            'name.string' => 'Nama siswa harus berupa teks',
            'name.max' => 'Nama siswa maksimal 255 karakter',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password wajib diisi',
            'password.string' => 'Password harus berupa teks',
            'password.min' => 'Password minimal 8 karakter',
            'nis.required' => 'NIS wajib diisi',
            'nis.string' => 'NIS harus berupa teks',
            'nis.unique' => 'NIS sudah terdaftar',
            'sekolah.required' => 'Sekolah wajib diisi',
            'sekolah.string' => 'Sekolah harus berupa teks',
            'sekolah.max' => 'Sekolah maksimal 255 karakter',
            'alamat.required' => 'Alamat wajib diisi',
            'alamat.string' => 'Alamat harus berupa teks',
            'alamat.max' => 'Alamat maksimal 500 karakter',
            'no_hp.required' => 'Nomor HP wajib diisi',
            'no_hp.string' => 'Nomor HP harus berupa teks',
            'no_hp.max' => 'Nomor HP maksimal 15 karakter',
        ];
    }
}
