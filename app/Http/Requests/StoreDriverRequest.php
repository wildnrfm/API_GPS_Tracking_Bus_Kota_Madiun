<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8',
            'no_hp' => ['required', 'string', 'max:15', Rule::unique('drivers', 'no_hp')],
            'alamat' => 'required|string|max:500',
            'nik' => ['required', 'string', 'max:20', Rule::unique('drivers', 'nik')],
        ];
    }

    public function messages() {
        return [
            'name.required' => 'Nama driver wajib diisi',
            'name.string' => 'Nama driver harus berupa teks',
            'name.max' => 'Nama driver maksimal 255 karakter',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'no_hp.required' => 'Nomor HP wajib diisi',
            'no_hp.unique' => 'Nomor HP sudah terdaftar',
            'no_hp.max' => 'Nomor HP maksimal 15 karakter',
            'alamat.required' => 'Alamat wajib diisi',
            'alamat.max' => 'Alamat maksimal 500 karakter',
            'nik.required' => 'NIK wajib diisi',
            'nik.unique' => 'NIK sudah terdaftar',
            'nik.max' => 'NIK maksimal 20 karakter',
        ];
    }
}
