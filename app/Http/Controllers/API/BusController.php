<?php

namespace App\Http\Controllers\API;

use App\Services\BusService;
use App\Constants\AppMessages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// CRUD operasi bus ( assignment driver, students), Business logic dihandle di BusService
class BusController extends BaseController {
    protected $busService;
    public function __construct(BusService $busService) {
        $this->busService = $busService;
        $this->middleware('auth:api');
    }

    // GET daftar semua bus dengan rute (support pagination)
    public function index(Request $request) {
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        
        // Get all buses (this calls the full mapping logic)
        $allBuses = $this->busService->getAllBuses();
        
        // Manually paginate the collection
        $total = count($allBuses);
        $items = collect($allBuses)->slice(($page - 1) * $perPage, $perPage)->values();
        
        // Create a LengthAwarePaginator instance
        $buses = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        return $this->responsePaginated($buses, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //GET detail bus dan rute
    public function show(Request $request, $id) {
        $bus = $this->busService->getBusById($id);
        return $this->responseSuccess($bus, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //create bus
    public function store(Request $request) {
        $data = $request->validate([
            'kode_bus' => 'required|string|max:20|unique:buses',
            'plat_nomor' => 'required|string|max:15|unique:buses',
            'status' => 'required|in:aktif,nonaktif,maintenance',
            'nama_rute' => 'sometimes|string|max:150',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'kode_bus.required' => 'Kode bus wajib diisi',
            'kode_bus.unique' => 'Kode bus sudah terdaftar',
            'plat_nomor.required' => 'Nomor plat wajib diisi',
            'plat_nomor.unique' => 'Nomor plat sudah terdaftar',
            'status.required' => 'Status wajib diisi',
            'status.in' => 'Status harus aktif, nonaktif, atau maintenance',
            'nama_rute.max' => 'Nama rute maksimal 150 karakter',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
            'photo.max' => 'Ukuran gambar maksimal 2MB',
        ]);

        // Handle photo upload — simpan langsung ke public/images/buses/
        // (tidak pakai Storage disk 'public' karena symlink NTFS gagal di server ini)
        if ($request->hasFile('photo')) {
            $photo    = $request->file('photo');
            $filename = uniqid('bus_', true) . '.' . $photo->getClientOriginalExtension();
            $destDir  = public_path('images/buses');
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            $photo->move($destDir, $filename);
            $data['photo'] = 'images/buses/' . $filename;
        }

        $result = $this->busService->createBus($data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated($result['bus'], AppMessages::SUCCESS_CREATED);
    }

    //update data bus
    public function update(Request $request, $id) {
        $this->busService->getBusById($id);
        $data = $request->validate([
            'kode_bus' => 'sometimes|string|max:20|unique:buses,kode_bus,' . $id,
            'plat_nomor' => 'sometimes|string|max:15|unique:buses,plat_nomor,' . $id,
            'status' => 'sometimes|in:aktif,nonaktif,maintenance',
            'nama_rute' => 'sometimes|nullable|string|max:150',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'kode_bus.unique' => 'Kode bus sudah terdaftar',
            'plat_nomor.unique' => 'Nomor plat sudah terdaftar',
            'status.in' => 'Status harus aktif, nonaktif, atau maintenance',
            'nama_rute.max' => 'Nama rute maksimal 150 karakter',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
            'photo.max' => 'Ukuran gambar maksimal 2MB',
        ]);

        // Handle photo upload — simpan langsung ke public/images/buses/
        if ($request->hasFile('photo')) {
            // Hapus foto lama jika ada
            $bus = \App\Models\Bus::find($id);
            if ($bus?->photo && file_exists(public_path($bus->photo))) {
                @unlink(public_path($bus->photo));
            }
            $photo    = $request->file('photo');
            $filename = uniqid('bus_', true) . '.' . $photo->getClientOriginalExtension();
            $destDir  = public_path('images/buses');
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            $photo->move($destDir, $filename);
            $data['photo'] = 'images/buses/' . $filename;
        }

        $result = $this->busService->updateBus($id, $data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseUpdated($result['bus'], AppMessages::SUCCESS_UPDATED);
    }

    // delete bus
    public function destroy(Request $request, $id) {
        $result = $this->busService->deleteBus($id);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }

    // update bus photo (accept photo path string, not file)
    public function updatePhoto(Request $request, $id) {
        if ($request->hasFile('photo')) {
            $data = $request->validate([
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
            $bus = \App\Models\Bus::find($id);
            if ($bus?->photo && file_exists(public_path($bus->photo))) {
                @unlink(public_path($bus->photo));
            }
            $photo    = $request->file('photo');
            $filename = uniqid('bus_', true) . '.' . $photo->getClientOriginalExtension();
            $destDir  = public_path('images/buses');
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $photo->move($destDir, $filename);
            $data['photo'] = 'images/buses/' . $filename;
        } else {
            $data = $request->validate([
                'photo' => 'required|string',
            ]);
        }

        $result = $this->busService->updateBus($id, $data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseUpdated($result['bus'], AppMessages::SUCCESS_UPDATED);
    }

    // get daftar siswa yang terdaftar di bus
    public function students(Request $request, $id) {
        $students = $this->busService->getBusStudents($id);
        return $this->responsePaginated($students, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get daftar driver yang pernah di bus ini
    public function drivers(Request $request, $id) {
        $drivers = $this->busService->getBusDrivers($id);
        return $this->responsePaginated($drivers, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get driver yang sedang aktif di bus ini
    public function activeDriver(Request $request, $id) {
        $activeDriver = $this->busService->getActiveDriver($id);
        if (!$activeDriver) {
            return $this->responseNotFound('Tidak ada driver aktif yang ditugaskan ke bus ini');
        }
        return $this->responseSuccess($activeDriver, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //post driver ke bus
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