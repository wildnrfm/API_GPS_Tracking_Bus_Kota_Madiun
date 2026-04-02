<?php

namespace App\Http\Controllers\API;

use App\Services\DriverService;
use App\Constants\AppMessages;
use App\Models\Bus;
use App\Models\Attendance;
use App\Models\GpsTrack;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

//menangani operasi driver (CRUD, bus assignments, history), business logic dihandle di DriverService
class DriverController extends BaseController {
    protected $driverService;
    public function __construct(DriverService $driverService) {
        $this->driverService = $driverService;
        $this->middleware('auth:api');
    }

    //Get daftar semua driver (admin only)
    public function index(Request $request) {
        $drivers = $this->driverService->getAllDrivers(15);
        return $this->responsePaginated($drivers, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //Get detail driver (admin only)
    public function show(Request $request, $id) {
        $driver = $this->driverService->getDriverById($id);
        return $this->responseSuccess($driver, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //create driver (admin only)
    public function store(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'nik' => ['required', 'string', Rule::unique('drivers', 'nik')],
            'no_hp' => 'required|string|max:15',
            'alamat' => 'required|string|max:500',
        ]);
        $result = $this->driverService->createDriver($data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated(
            ['user' => $result['user'], 'driver' => $result['driver']],
            AppMessages::SUCCESS_DRIVER_CREATED
        );
    }

    //Update driver (admin only)
    public function update(Request $request, $id) {
        $rules = [];
        $messages = [];
        if ($request->has('name')) {
            $rules['name'] = 'required|string|max:255';
            $messages['name.required'] = AppMessages::ERROR_NAME_REQUIRED;
            $messages['name.max'] = AppMessages::ERROR_NAME_TOO_LONG;
        }
        if ($request->has('email')) {
            $rules['email'] = ['required', 'email', Rule::unique('users', 'email')->ignore($id)];
            $messages['email.required'] = AppMessages::ERROR_EMAIL_REQUIRED;
            $messages['email.email'] = AppMessages::ERROR_EMAIL_INVALID;
            $messages['email.unique'] = AppMessages::ERROR_EMAIL_TAKEN;
        }
        if ($request->has('password')) {
            $rules['password'] = 'required|string|min:8|confirmed';
            $rules['password_confirmation'] = 'required|string';
            $messages['password.required'] = AppMessages::ERROR_PASSWORD_REQUIRED;
            $messages['password.min'] = AppMessages::ERROR_PASSWORD_WEAK;
            $messages['password.confirmed'] = AppMessages::ERROR_PASSWORD_MISMATCH;
            $messages['password_confirmation.required'] = 'Password confirmation harus diisi';
        }
        if ($request->has('nik')) {
            $rules['nik'] = ['required', 'string', Rule::unique('drivers', 'nik')->ignore($id)];
            $messages['nik.required'] = 'NIK harus diisi';
            $messages['nik.unique'] = 'NIK sudah terdaftar';
        }
        if ($request->has('no_hp')) {
            $rules['no_hp'] = 'sometimes|string|max:15';
            $messages['no_hp.max'] = 'Nomor HP terlalu panjang';
        }
        if ($request->has('alamat')) {
            $rules['alamat'] = 'sometimes|string|max:500';
            $messages['alamat.max'] = 'Alamat terlalu panjang';
        }
        if (empty($rules)) {
            return $this->responseError('Tidak ada data yang dapat diupdate', null, 422);
        }
        $data = $request->validate($rules, $messages);
        $result = $this->driverService->updateDriver($id, $data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseUpdated(
            ['user' => $result['user'], 'driver' => $result['driver']],
            AppMessages::SUCCESS_DRIVER_UPDATED
        );
    }

    //delete driver (admin only)
    public function destroy(Request $request, $id) {
        $result = $this->driverService->deleteDriver($id);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseDeleted(AppMessages::SUCCESS_DRIVER_DELETED);
    }

    //Get bus assignments history dari driver (admin only)
    public function history(Request $request, $id) {
        $history = $this->driverService->getDriverBusHistory($id);
        return $this->responsePaginated($history, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // get profile driver yang sedang login
    public function meDriver(Request $request) {
        $user = $request->user();
        $driver = $user->driver()->with('user')->first();
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $data = [
            'id' => $driver->id,
            'user' => [
                'id' => $driver->user->id,
                'name' => $driver->user->name,
                'email' => $driver->user->email,
            ],
            'nik' => $driver->nik,
            'no_hp' => $driver->no_hp,
            'alamat' => $driver->alamat,
            'created_at' => $driver->created_at,
        ];
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get bus assignments aktif dari driver yang login
    public function myBuses(Request $request) {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $assignments = $this->driverService->getActiveAssignments($driver->id);
        $data = $this->driverService->mapBusAssignmentsData($assignments);
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //Toggle GPS status dari bus yang ditugaskan
    public function toggleGpsStatus(Request $request) {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $data = $request->validate([
            'gps_status' => 'required|in:on,off',
        ]);
        $assignment = $driver->buses()->wherePivotNull('tanggal_selesai')->orWherePivot('tanggal_selesai', '>=', now()->toDateString())->first();
        if (!$assignment) {
            return $this->responseForbidden(AppMessages::ERROR_BUS_NOT_ASSIGNED);
        }
        if ($data['gps_status'] === 'on') {
            // Catat GPS awal saat driver aktifkan tracking
            // Gunakan $assignment->pivot->bus_id (bukan $assignment->id yang merupakan ID pivot row)
            GpsTrack::create([
                'bus_id'      => $assignment->pivot->bus_id,
                'latitude'    => 0.0,
                'longitude'   => 0.0,
                'speed'       => 0,
                'recorded_at' => now(),
            ]);
        }
        $assignment->pivot->update([
            'gps_status' => $data['gps_status'],
            'last_gps_update' => $data['gps_status'] === 'on' ? now() : $assignment->pivot->last_gps_update,
        ]);
        return $this->responseSuccess([
            'gps_status' => $data['gps_status'],
            'last_gps_update' => $assignment->pivot->last_gps_update,
        ], AppMessages::SUCCESS_GPS_STATUS_UPDATED);
    }

    //Get daily report untuk bus
    public function dailyReport(Request $request, $busId) {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->responseNotFound(AppMessages::ERROR_DRIVER_NOT_FOUND);
        }
        $bus = Bus::findOrFail($busId);
        $assignment = $driver->buses()->where('bus_id', $busId)->first();
        if (!$assignment) {
            return $this->responseForbidden(AppMessages::ERROR_BUS_NOT_ASSIGNED);
        }
        $date = $request->input('date', now()->toDateString());
        $attendance = Attendance::select(
            'id','student_id','bus_id','tanggal',
            'halte_naik_id','waktu_naik','waktu_turun',
            'lat_naik','lng_naik','lat_turun','lng_turun'
        )->where('bus_id', $busId)->whereDate('tanggal', $date)->get();
        $totalStudents = $bus->students()->count();
        $attendanceDetails = $attendance->map(function($att) {
            $student = $att->student;
            $userName = $student && $student->user ? $student->user->name : 'Tidak Diketahui';
            $nis = $student ? $student->nis : 'N/A';
            return [
                'student_id' => $att->student_id,
                'student_name' => $userName,
                'student_nis' => $nis,
                'tanggal' => $att->tanggal,
                'waktu_naik' => $att->waktu_naik,
                'waktu_turun' => $att->waktu_turun,
                'halte_naik' => $att->halte_naik_id,
                'lat_naik' => $att->lat_naik,
                'lng_naik' => $att->lng_naik,
                'lat_turun' => $att->lat_turun,
                'lng_turun' => $att->lng_turun,
            ];
        });
        $data = [
            'bus' => [
                'id' => $bus->id,
                'kode_bus' => $bus->kode_bus,
                'plat_nomor' => $bus->plat_nomor,
            ],
            'report_date' => $date,
            'total_students_assigned' => $totalStudents,
            'total_attendance' => $attendance->count(),
            'attendance_details' => $attendanceDetails
        ];
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }
}