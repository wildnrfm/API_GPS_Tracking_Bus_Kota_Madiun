<?php

namespace App\Http\Controllers\API;

use App\Services\BusService;
use App\Constants\AppMessages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use App\Models\Bus;

class BusController extends BaseController {
    protected $busService;
    public function __construct(BusService $busService) {
        $this->busService = $busService;
        $this->middleware('auth:api');
    }

    public function index(Request $request) {
        $buses = $this->busService->getAllBuses();
        return $this->responseSuccess($buses, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    public function show(Request $request, $id) {
        $bus = $this->busService->getBusById($id);
        return $this->responseSuccess($bus, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'kode_bus' => 'required|string|max:20|unique:buses',
            'plat_nomor' => 'required|string|max:15|unique:buses',
            'status' => 'required|in:aktif,nonaktif,maintenance',
            'nama_rute' => 'sometimes|string|max:150',
        ], [
            'kode_bus.required' => 'Kode bus wajib diisi',
            'kode_bus.unique' => 'Kode bus sudah terdaftar',
            'plat_nomor.required' => 'Nomor plat wajib diisi',
            'plat_nomor.unique' => 'Nomor plat sudah terdaftar',
            'status.required' => 'Status wajib diisi',
            'status.in' => 'Status harus aktif, nonaktif, atau maintenance',
            'nama_rute.max' => 'Nama rute maksimal 150 karakter',
        ]);
        $result = $this->busService->createBus($data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated($result['bus'], AppMessages::SUCCESS_CREATED);
    }

    public function update(Request $request, $id) {
        $this->busService->getBusById($id);
        $data = $request->validate([
            'kode_bus' => 'sometimes|string|max:20|unique:buses,kode_bus,' . $id,
            'plat_nomor' => 'sometimes|string|max:15|unique:buses,plat_nomor,' . $id,
            'status' => 'sometimes|in:aktif,nonaktif,maintenance',
            'nama_rute' => 'sometimes|nullable|string|max:150',
        ], [
            'kode_bus.unique' => 'Kode bus sudah terdaftar',
            'plat_nomor.unique' => 'Nomor plat sudah terdaftar',
            'status.in' => 'Status harus aktif, nonaktif, atau maintenance',
            'nama_rute.max' => 'Nama rute maksimal 150 karakter',
        ]);
        $result = $this->busService->updateBus($id, $data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseUpdated($result['bus'], AppMessages::SUCCESS_UPDATED);
    }

    public function destroy(Request $request, $id) {
        $result = $this->busService->deleteBus($id);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }

    public function uploadPhoto(Request $request, $id) {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'photo.required' => 'Foto wajib diisi',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format foto harus jpeg, png, atau jpg',
            'photo.max' => 'Ukuran foto maksimal 2MB',
        ]);

        $bus = Bus::findOrFail($id);

        if ($bus->photo && Storage::disk('public')->exists($bus->photo)) {
            Storage::disk('public')->delete($bus->photo);
        }

        $path = $request->file('photo')->store('buses/photos', 'public');
        $bus->photo = $path;
        $bus->save();

        return $this->responseSuccess([
            'photo_url' => asset('storage/' . $path),
        ], 'Foto bus berhasil diupdate');
    }

    public function students(Request $request, $id) {
        $students = $this->busService->getBusStudents($id);
        return $this->responsePaginated($students, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    public function drivers(Request $request, $id) {
        $drivers = $this->busService->getBusDrivers($id);
        return $this->responsePaginated($drivers, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    public function activeDriver(Request $request, $id) {
        $activeDriver = $this->busService->getActiveDriver($id);
        if (!$activeDriver) {
            return $this->responseNotFound('Tidak ada driver aktif yang ditugaskan ke bus ini');
        }
        return $this->responseSuccess($activeDriver, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    public function assignDriver(Request $request, $id) {
        $data = $request->validate([
            'driver_id' => ['required', Rule::exists('drivers', 'id')],
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
        ], [
            'driver_id.required' => 'Driver wajib diisi',
            'driver_id.exists' => 'Driver tidak ditemukan',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi',
            'tanggal_mulai.date' => 'Tanggal mulai harus format tanggal valid',
            'tanggal_selesai.date' => 'Tanggal selesai harus format tanggal valid',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus setelah atau sama dengan tanggal mulai',
        ]);
        $result = $this->busService->assignDriverToBus(
            $id,
            $data['driver_id'],
            $data['tanggal_mulai'],
            $data['tanggal_selesai'] ?? null
        );
        if (!$result['success']) {
            if (isset($result['conflicting_assignment'])) {
                return $this->responseConflict(
                    $result['conflicting_assignment'],
                    'Driver sudah ditugaskan ke bus lain'
                );
            }
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated(
            $result['assignment'],
            'Driver berhasil ditugaskan ke bus'
        );
    }
}