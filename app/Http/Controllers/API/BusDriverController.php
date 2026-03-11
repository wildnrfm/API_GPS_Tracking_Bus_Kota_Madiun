<?php

namespace App\Http\Controllers\API;

use App\Constants\AppMessages;
use App\Models\BusDriver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

//penugasan driver ke bus (record driver assignments), admin only
class BusDriverController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    //get daftar penugasan driver (active/expired)
    public function index(Request $request) {
        $this->authorizeAdmin($request);
        $status = $request->query('status', 'active');
        $query = BusDriver::with('bus', 'driver.user');
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'expired') {
            $query->expired();
        }
        $assignments = $query->paginate(15);
        return $this->responsePaginated($assignments, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //create penugasan driver ke bus
    public function store(Request $request) {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'driver_id' => ['required', Rule::exists('drivers', 'id')],
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ], [
            'bus_id.required' => 'Bus wajib diisi',
            'bus_id.exists' => 'Bus tidak ditemukan',
            'driver_id.required' => 'Driver wajib diisi',
            'driver_id.exists' => 'Driver tidak ditemukan',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date' => 'Tanggal mulai harus format tanggal valid',
            'tanggal_selesai.date' => 'Tanggal selesai harus format tanggal valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai',
        ]);
        $busDriver = BusDriver::create($data);
        return $this->responseCreated($busDriver, AppMessages::SUCCESS_CREATED);
    }

    //update penugasan driver
    public function update(Request $request, $id) {
        $this->authorizeAdmin($request);
        $busDriver = BusDriver::findOrFail($id);
        $data = $request->validate([
            'tanggal_mulai' => 'sometimes|date',
            'tanggal_selesai' => 'sometimes|nullable|date|after_or_equal:tanggal_mulai',
        ], [
            'tanggal_mulai.date' => 'Tanggal mulai harus format tanggal valid',
            'tanggal_selesai.date' => 'Tanggal selesai harus format tanggal valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai',
        ]);
        $busDriver->update($data);
        return $this->responseUpdated($busDriver, AppMessages::SUCCESS_UPDATED);
    }

    //delete penugasan driver
    public function destroy(Request $request, $id) {
        $this->authorizeAdmin($request);
        $busDriver = BusDriver::findOrFail($id);
        $busDriver->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}
