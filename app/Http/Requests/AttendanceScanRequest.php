<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceScanRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules() {
        return [
            'student_id' => 'required|integer|exists:students,id',
            'bus_id' => 'required|integer|exists:buses,id',
            'halte_id' => 'required|integer|exists:haltes,id',
            'barcode' => 'required|string',
            'scan_type' => 'required|in:naik,turun',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function messages() {
        return [
            'student_id.required' => 'ID siswa wajib diisi',
            'student_id.integer' => 'ID siswa harus berupa angka',
            'student_id.exists' => 'Siswa tidak ditemukan',
            'bus_id.required' => 'ID bus wajib diisi',
            'bus_id.integer' => 'ID bus harus berupa angka',
            'bus_id.exists' => 'Bus tidak ditemukan',
            'halte_id.required' => 'ID halte wajib diisi',
            'halte_id.integer' => 'ID halte harus berupa angka',
            'halte_id.exists' => 'Halte tidak ditemukan',
            'barcode.required' => 'Barcode wajib diisi',
            'barcode.string' => 'Barcode harus berupa teks',
            'scan_type.required' => 'Tipe scan (naik/turun) wajib diisi',
            'scan_type.in' => 'Tipe scan harus naik atau turun',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus antara -90 dan 90',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus antara -180 dan 180',
        ];
    }
}
