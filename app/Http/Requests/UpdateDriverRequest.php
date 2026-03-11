<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest {
    public function authorize(){
        return true;
    }

    public function rules() {
        $driverId = $this->route('id');
        return [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore(auth()->id())],
            'no_hp' => ['sometimes', 'string', 'max:15', Rule::unique('drivers', 'no_hp')->ignore($driverId)],
            'alamat' => 'sometimes|string|max:500',
            'nik' => ['sometimes', 'string', 'max:20', Rule::unique('drivers', 'nik')->ignore($driverId)],
        ];
    }

    public function messages(){
        return [
            'name.string' => 'Nama driver harus berupa teks',
            'name.max' => 'Nama driver maksimal 255 karakter',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah terdaftar',
            'no_hp.unique' => 'Nomor HP sudah terdaftar',
            'no_hp.max' => 'Nomor HP maksimal 15 karakter',
            'alamat.max' => 'Alamat maksimal 500 karakter',
            'nik.unique' => 'NIK sudah terdaftar',
            'nik.max' => 'NIK maksimal 20 karakter',
        ];
    }
}
