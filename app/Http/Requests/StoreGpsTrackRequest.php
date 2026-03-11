<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGpsTrackRequest extends FormRequest {
    public function authorize() {
        return true;
    }

    public function rules(){
        return [
            'bus_id' => 'required|integer|exists:buses,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'altitude' => 'nullable|numeric',
            'heading' => 'nullable|numeric|between:0,360',
        ];
    }

    public function messages() {
        return [
            'bus_id.required' => 'ID bus wajib diisi',
            'bus_id.integer' => 'ID bus harus berupa angka',
            'bus_id.exists' => 'Bus tidak ditemukan',
            'latitude.required' => 'Latitude wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus antara -90 dan 90',
            'longitude.required' => 'Longitude wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus antara -180 dan 180',
            'accuracy.numeric' => 'Accuracy harus berupa angka',
            'accuracy.min' => 'Accuracy harus lebih besar dari 0',
            'speed.numeric' => 'Kecepatan harus berupa angka',
            'speed.min' => 'Kecepatan harus lebih besar dari 0',
            'altitude.numeric' => 'Altitude harus berupa angka',
            'heading.numeric' => 'Heading harus berupa angka',
            'heading.between' => 'Heading harus antara 0 dan 360',
        ];
    }
}
