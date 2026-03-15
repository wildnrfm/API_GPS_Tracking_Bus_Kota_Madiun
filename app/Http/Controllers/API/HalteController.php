<?php

namespace App\Http\Controllers\API;

use App\Constants\AppMessages;
use App\Models\Halte;
use Illuminate\Http\Request;

//CRUD data halte, admin only
class HalteController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    //get daftar semua halte (tanpa pagination agar FE bisa load semua sekaligus)
    public function index(Request $request) {
        $this->authorizeAdmin($request);
        $haltes = Halte::orderBy('nama_halte')->get();
        return $this->responseSuccess($haltes, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get detail halte 
    public function show(Request $request, $id) {
        $this->authorizeAdmin($request);
        $halte = Halte::findOrFail($id);
        return $this->responseSuccess($halte, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //create halte
    public function store(Request $request) {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'nama_halte' => 'required|string|max:150',
            'alamat'     => 'nullable|string|max:500',
            'latitude'   => 'required|numeric|between:-90,90',
            'longitude'  => 'required|numeric|between:-180,180',
        ], [
            'nama_halte.required' => 'Nama halte wajib diisi',
            'nama_halte.max'      => 'Nama halte maksimal 150 karakter',
            'latitude.required'   => 'Latitude wajib diisi',
            'latitude.numeric'    => 'Latitude harus berupa angka',
            'latitude.between'    => 'Latitude harus antara -90 dan 90',
            'longitude.required'  => 'Longitude wajib diisi',
            'longitude.numeric'   => 'Longitude harus berupa angka',
            'longitude.between'   => 'Longitude harus antara -180 dan 180',
        ]);
        // Pastikan alamat tidak null untuk kolom text (default string kosong)
        $data['alamat'] = $data['alamat'] ?? '';
        $halte = Halte::create($data);
        return $this->responseCreated($halte, AppMessages::SUCCESS_CREATED);
    }

    //update data halte
    public function update(Request $request, $id) {
        $this->authorizeAdmin($request);
        $halte = Halte::findOrFail($id);
        $data = $request->validate([
            'nama_halte' => 'sometimes|string|max:150',
            'alamat' => 'sometimes|string|max:500',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
        ], [
            'nama_halte.max' => 'Nama halte maksimal 150 karakter',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus antara -90 dan 90',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus antara -180 dan 180',
        ]);
        $halte->update($data);
        return $this->responseUpdated($halte, AppMessages::SUCCESS_UPDATED);
    }

    //delete halte
    public function destroy(Request $request, $id) {
        $this->authorizeAdmin($request);
        $halte = Halte::findOrFail($id);
        $halte->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}