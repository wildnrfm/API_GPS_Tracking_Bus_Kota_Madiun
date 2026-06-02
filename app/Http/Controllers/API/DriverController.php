<?php

namespace App\Http\Controllers\API;

use App\Services\DriverService;
use App\Services\BusService;
use App\Constants\AppMessages;
use App\Models\Bus;
use App\Models\BusDriver;
use App\Models\Attendance;
use App\Models\GpsTrack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends BaseController
{
    protected $driverService;
    protected $busService;

    public function __construct(DriverService $driverService, BusService $busService)
    {
        $this->driverService = $driverService;
        $this->busService    = $busService;
        $this->middleware('auth:api');
    }

    // GET daftar semua driver
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 1000);
        $drivers = $this->driverService->getAllDrivers($perPage);
        return $this->responsePaginated($drivers, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // GET detail driver
    public function show(Request $request, $id)
    {
        $driver = $this->driverService->getDriverById($id);
        return $this->responseSuccess($driver, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // POST buat driver baru
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'nik'      => ['required', 'string', Rule::unique('drivers', 'nik')],
            'no_hp'    => 'required|string|max:15',
            'alamat'   => 'required|string|max:500',
            'photo'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $photo    = $request->file('photo');
            $filename = uniqid('driver_', true) . '.' . $photo->getClientOriginalExtension();
            $destDir  = public_path('images/driver');
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            $photo->move($destDir, $filename);
            $data['photo'] = 'images/driver/' . $filename;
        }

        $result = $this->driverService->createDriver($data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated(
            ['user' => $result['user'], 'driver' => $result['driver']],
            AppMessages::SUCCESS_DRIVER_CREATED
        );
    }

    // PUT/PATCH update data driver (support driver_id & user_id)
    public function update(Request $request, $id)
    {
        $rules    = [];
        $messages = [];

        // Resolve driver: support lookup by driver_id atau user_id (Web Admin & Flutter)
        $driverByDriverId = \App\Models\Driver::find($id);
        $driverByUserId   = \App\Models\Driver::where('user_id', $id)->first();

        $driver = null;
        if ($driverByDriverId && !$driverByUserId) {
            $driver = $driverByDriverId;
        } elseif (!$driverByDriverId && $driverByUserId) {
            $driver = $driverByUserId;
        } elseif ($driverByDriverId && $driverByUserId) {
            // Keduanya match: resolve via email atau NIK dari request
            $requestNik   = $request->input('nik');
            $requestEmail = $request->input('email');

            if ($requestEmail) {
                if ($driverByDriverId->user && $driverByDriverId->user->email === $requestEmail) {
                    $driver = $driverByDriverId;
                } elseif ($driverByUserId->user && $driverByUserId->user->email === $requestEmail) {
                    $driver = $driverByUserId;
                } else {
                    $driver = $driverByDriverId;
                }
            } elseif ($requestNik) {
                if ($driverByDriverId->nik === $requestNik) {
                    $driver = $driverByDriverId;
                } elseif ($driverByUserId->nik === $requestNik) {
                    $driver = $driverByUserId;
                }
            }

            if (!$driver) {
                $driver = $request->has('email') ? $driverByDriverId : $driverByUserId;
            }
        }

        $userId   = $driver ? $driver->user_id : $id;
        $driverId = $driver ? $driver->id : $id;

        if ($request->has('name')) {
            $rules['name']           = 'required|string|max:255';
            $messages['name.required'] = AppMessages::ERROR_NAME_REQUIRED;
            $messages['name.max']    = AppMessages::ERROR_NAME_TOO_LONG;
        }
        if ($request->has('email')) {
            $rules['email']            = ['required', 'email', Rule::unique('users', 'email')->ignore($userId)];
            $messages['email.required'] = AppMessages::ERROR_EMAIL_REQUIRED;
            $messages['email.email']   = AppMessages::ERROR_EMAIL_INVALID;
            $messages['email.unique']  = AppMessages::ERROR_EMAIL_TAKEN;
        }
        if ($request->has('password')) {
            $rules['password']                       = 'required|string|min:8|confirmed';
            $rules['password_confirmation']          = 'required|string';
            $messages['password.required']           = AppMessages::ERROR_PASSWORD_REQUIRED;
            $messages['password.min']                = AppMessages::ERROR_PASSWORD_WEAK;
            $messages['password.confirmed']          = AppMessages::ERROR_PASSWORD_MISMATCH;
            $messages['password_confirmation.required'] = 'Password confirmation harus diisi';
        }
        if ($request->has('nik')) {
            $rules['nik']            = ['required', 'string', Rule::unique('drivers', 'nik')->ignore($driverId)];
            $messages['nik.required'] = 'NIK harus diisi';
            $messages['nik.unique']  = 'NIK sudah terdaftar';
        }
        if ($request->has('no_hp')) {
            $rules['no_hp']       = 'sometimes|string|max:15';
            $messages['no_hp.max'] = 'Nomor HP terlalu panjang';
        }
        if ($request->has('alamat')) {
            $rules['alamat']       = 'sometimes|string|max:500';
            $messages['alamat.max'] = 'Alamat terlalu panjang';
        }
        if ($request->hasFile('photo') || $request->has('photo')) {
            $rules['photo']            = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048';
            $messages['photo.image']   = 'File harus berupa gambar';
            $messages['photo.mimes']   = 'Format gambar harus jpeg, png, jpg, atau gif';
            $messages['photo.max']     = 'Ukuran gambar maksimal 2MB';
        }
        if (empty($rules)) {
            return $this->responseError('Tidak ada data yang dapat diupdate', null, 422);
        }

        $data = $request->validate($rules, $messages);

        if ($request->hasFile('photo')) {
            if ($driver && $driver->user?->photo && file_exists(public_path($driver->user->photo))) {
                @unlink(public_path($driver->user->photo));
            }
            $photo    = $request->file('photo');
            $filename = uniqid('driver_', true) . '.' . $photo->getClientOriginalExtension();
            $destDir  = public_path('images/driver');
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            $photo->move($destDir, $filename);
            $data['photo'] = 'images/driver/' . $filename;
        }

        $result = $this->driverService->updateDriver($userId, $data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseUpdated(
            ['user' => $result['user'], 'driver' => $result['driver']],
            AppMessages::SUCCESS_DRIVER_UPDATED
        );
    }

    // DELETE driver (support driver_id & user_id)
    public function destroy(Request $request, $id)
    {
        // Resolve driver via driver_id atau user_id; DELETE tidak punya payload NIK/email
        $driverByDriverId = \App\Models\Driver::find($id);
        $driverByUserId   = \App\Models\Driver::where('user_id', $id)->first();

        $driver = null;
        if ($driverByDriverId && !$driverByUserId) {
            $driver = $driverByDriverId;
        } elseif (!$driverByDriverId && $driverByUserId) {
            $driver = $driverByUserId;
        } elseif ($driverByDriverId && $driverByUserId) {
            // Bedakan Web Admin (browser/curl) vs Flutter (dart) via User-Agent
            $isDart = str_contains(strtolower($request->header('User-Agent', '')), 'dart');
            $driver = $isDart ? $driverByUserId : $driverByDriverId;
        }

        $userId = $driver ? $driver->user_id : $id;

        $result = $this->driverService->deleteDriver($userId);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseDeleted(AppMessages::SUCCESS_DRIVER_DELETED);
    }

    // GET riwayat bus driver
    public function history(Request $request, $id)
    {
        $history = $this->driverService->getDriverBusHistory($id);
        return $this->responsePaginated($history, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // GET profil driver yang sedang login
    public function meDriver(Request $request)
    {
        $user   = $request->user();
        $driver = $user->driver()->with('user')->first();
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $data = [
            'id'   => $driver->id,
            'user' => [
                'id'    => $driver->user->id,
                'name'  => $driver->user->name,
                'email' => $driver->user->email,
            ],
            'nik'        => $driver->nik,
            'no_hp'      => $driver->no_hp,
            'alamat'     => $driver->alamat,
            'created_at' => $driver->created_at,
        ];
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // GET bus aktif milik driver yang login
    public function myBuses(Request $request)
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $assignments = $this->driverService->getActiveAssignments($driver->id);
        $data        = $this->driverService->mapBusAssignmentsData($assignments);
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // POST toggle status GPS driver
    public function toggleGpsStatus(Request $request)
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $data = $request->validate([
            'gps_status' => 'required|in:on,off',
        ]);

        $busDriver = BusDriver::where('driver_id', $driver->id)
            ->active()
            ->latest('created_at')
            ->first();
        if (!$busDriver) {
            return $this->responseForbidden(AppMessages::ERROR_BUS_NOT_ASSIGNED);
        }
        $bus = Bus::find($busDriver->bus_id);
        if (!$bus || $bus->status !== 'aktif') {
            return $this->responseForbidden('Bus tidak aktif');
        }

        // last_gps_update selalu diperbarui (ON & OFF) agar stale-check 30 detik akurat
        $busDriver->update([
            'gps_status'      => $data['gps_status'],
            'last_gps_update' => now(),
        ]);

        $busDriver->load('bus:id,kode_bus,plat_nomor');
        $driverUser = $driver->user ?? $request->user();

        return $this->responseSuccess([
            'gps_status'      => $data['gps_status'],
            'last_gps_update' => $busDriver->last_gps_update,
            'driver_name'     => $driverUser->name ?? '',
            'bus_code'        => $busDriver->bus->kode_bus ?? '',
            'bus_id'          => $busDriver->bus_id,
        ], AppMessages::SUCCESS_GPS_STATUS_UPDATED);
    }

    // GET laporan harian driver untuk bus tertentu
    public function dailyReport(Request $request, $busId)
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $bus = Bus::findOrFail($busId);
        $assignment = $driver->buses()->where('bus_id', $busId)->first();
        if (!$assignment) {
            return $this->responseForbidden(AppMessages::ERROR_BUS_NOT_ASSIGNED);
        }
        $date       = $request->input('date', now()->toDateString());
        $attendance = Attendance::select(
            'id', 'student_id', 'bus_id', 'tanggal',
            'halte_naik_id', 'waktu_naik', 'waktu_turun',
            'lat_naik', 'lng_naik', 'lat_turun', 'lng_turun'
        )->where('bus_id', $busId)->whereDate('tanggal', $date)->get();

        $totalStudents     = $bus->students()->count();
        $attendanceDetails = $attendance->map(function ($att) {
            $student  = $att->student;
            $userName = $student && $student->user ? $student->user->name : 'Tidak Diketahui';
            $nis      = $student ? $student->nis : 'N/A';
            return [
                'student_id'   => $att->student_id,
                'student_name' => $userName,
                'student_nis'  => $nis,
                'tanggal'      => $att->tanggal,
                'waktu_naik'   => $att->waktu_naik,
                'waktu_turun'  => $att->waktu_turun,
                'halte_naik'   => $att->halte_naik_id,
                'lat_naik'     => $att->lat_naik,
                'lng_naik'     => $att->lng_naik,
                'lat_turun'    => $att->lat_turun,
                'lng_turun'    => $att->lng_turun,
            ];
        });

        $data = [
            'bus' => [
                'id'         => $bus->id,
                'kode_bus'   => $bus->kode_bus,
                'plat_nomor' => $bus->plat_nomor,
            ],
            'report_date'             => $date,
            'total_students_assigned' => $totalStudents,
            'total_attendance'        => $attendance->count(),
            'attendance_details'      => $attendanceDetails,
        ];
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // GET daftar siswa di bus milik driver yang login
    public function myBusStudents(Request $request, $busId)
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $assignment = $driver->buses()->where('bus_id', $busId)->first();
        if (!$assignment) {
            return $this->responseForbidden(AppMessages::ERROR_BUS_NOT_ASSIGNED);
        }
        $students = $this->busService->getBusStudents($busId);
        return $this->responsePaginated($students, AppMessages::SUCCESS_DATA_RETRIEVED);
    }
}