<?php

namespace App\Http\Controllers\API;

use App\Services\StudentService;
use App\Constants\AppMessages;
use App\Models\GpsTrack;
use App\Models\BusDriver;
use App\Models\Attendance;
use App\Models\Halte;
use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

// CRUD operasi siswa (approval, barcode, bus tracking), business logic dihandle di StudentService
class StudentController extends BaseController {
    protected $studentService;
    public function __construct(StudentService $studentService) {
        $this->studentService = $studentService;
        $this->middleware('auth:api');
    }

    //Get semua siswa 
    public function index(Request $request) {
        $students = $this->studentService->getAllStudents(15);
        return $this->responsePaginated($students, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // detail siswa 
    public function show(Request $request, $id) {
        $student = $this->studentService->getStudentById($id);
        return $this->responseSuccess($student, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //tambah siswa baru 
    public function store(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'nis' => ['required', 'string', Rule::unique('students', 'nis')],
            'sekolah' => 'required|string|max:255',
            'kelas' => 'sometimes|string|max:100',
            'alamat' => 'required|string|max:500',
            'no_hp' => 'required|string|max:15',
        ]);
        $result = $this->studentService->createStudent($data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated(
            ['user' => $result['user'], 'student' => $result['student']],
            AppMessages::SUCCESS_CREATED
        );
    }

    // Update siswa 
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
        if ($request->has('nis')) {
            $rules['nis'] = ['required', 'string', Rule::unique('students', 'nis')->ignore($id)];
            $messages['nis.required'] = 'NIS harus diisi';
            $messages['nis.unique'] = 'NIS sudah terdaftar';
        }
        if ($request->has('sekolah')) {
            $rules['sekolah'] = 'sometimes|string|max:255';
            $messages['sekolah.max'] = 'Nama sekolah terlalu panjang';
        }
        if ($request->has('kelas')) {
            $rules['kelas'] = 'sometimes|string|max:100';
            $messages['kelas.max'] = 'Kelas terlalu panjang';
        }
        if ($request->has('alamat')) {
            $rules['alamat'] = 'sometimes|string|max:500';
            $messages['alamat.max'] = 'Alamat terlalu panjang';
        }
        if ($request->has('no_hp')) {
            $rules['no_hp'] = 'sometimes|string|max:15';
            $messages['no_hp.max'] = 'Nomor HP terlalu panjang';
        }
        if (empty($rules)) {
            return $this->responseError('Tidak ada data yang dapat diupdate', null, 422);
        }
        $data = $request->validate($rules, $messages);
        $result = $this->studentService->updateStudent($id, $data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseUpdated(
            ['user' => $result['user'], 'student' => $result['student']],
            AppMessages::SUCCESS_UPDATED
        );
    }

    //delete siswa 
    public function destroy(Request $request, $id) {
        $result = $this->studentService->deleteStudent($id);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }

    //Get daftar siswa pending approval 
    public function pending(Request $request) {
        $students = $this->studentService->getPendingStudents(15);
        return $this->responsePaginated($students, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Approve siswa 
    public function approve(Request $request, $id) {
        $result = $this->studentService->approveStudent($id);
        if (!$result['success']) {
            return $this->responseConflict($result['error']);
        }
        return $this->responseSuccess(
            $result['student'],
            'Siswa berhasil disetujui'
        );
    }

    // Reject siswa 
    public function reject(Request $request, $id) {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi',
            'reason.max' => 'Alasan maksimal 500 karakter',
        ]);
        $result = $this->studentService->rejectStudent($id, $data['reason']);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 400);
        }
        return $this->responseSuccess(
            $result['student'],
            'Siswa berhasil ditolak'
        );
    }

    // Suspend (nonaktifkan) siswa
    public function suspend(Request $request, $id) {
        $student = \App\Models\Student::where('user_id', $id)->firstOrFail();
        $user = $student->user;
        $user->is_suspended = true;
        $user->save();
        return $this->responseSuccess(
            ['user_id' => $id, 'is_suspended' => true],
            'Siswa berhasil dinonaktifkan'
        );
    }

    // Unsuspend (aktifkan kembali) siswa
    public function unsuspend(Request $request, $id) {
        $student = \App\Models\Student::where('user_id', $id)->firstOrFail();
        $user = $student->user;
        $user->is_suspended = false;
        $user->save();
        return $this->responseSuccess(
            ['user_id' => $id, 'is_suspended' => false],
            'Siswa berhasil diaktifkan kembali'
        );
    }

    //Generate barcode siswa 
    public function barcode(Request $request, $id) {
        $barcodeText = $this->studentService->generateBarcodeText($id);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($barcodeText);
        $qrCodeData = @file_get_contents($qrCodeUrl);
        if ($qrCodeData === false) {
            return $this->responseError('Gagal membuat QR code', null, 500);
        }
        return response($qrCodeData, 200)->header('Content-Type', 'image/png')->header('Content-Disposition', 'inline; filename="qrcode-' . $barcodeText . '.png"');
    }

    //Get data siswa yang sedang login
    public function meStudent(Request $request) {
        $user = $request->user();
        $student = $user->student;
        return $this->responseSuccess([
            'id' => $student->id,
            'nis' => $student->nis,
            'sekolah' => $student->sekolah,
            'kelas' => $student->kelas,
            'alamat' => $student->alamat,
            'no_hp' => $student->no_hp,
            'approval_status' => $student->approval_status,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ], AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //Generate QR code untuk check-in siswa
    //Auto-detect bus & halte terdekat dari GPS siswa,Input latitude & longitude saja
    public function myBarcode(Request $request) {

        //Validate GPS input dengan pesan custom
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'latitude.required' => 'Latitude (lintang) wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus berada antara -90 sampai 90',
            'longitude.required' => 'Longitude (bujur) wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus berada antara -180 sampai 180',
        ]);
        $user = $request->user();
        $student = $user->student;
        $today = now()->toDateString();

        //cek pakah siswa punya attendance hari ini yang belum checkout?
        $existingAttendance = Attendance::where('student_id', $student->id)->where('tanggal', $today)->whereNull('waktu_turun')->first();
        if ($existingAttendance) {
            return $this->responseError(
                'Anda masih dalam perjalanan. Silakan turunkan terlebih dahulu sebelum naik lagi.',
                409
            );
        }

        //Auto-detect bus dari StudentBus assignment (student hanya boleh di-assign ke 1 bus aktif)
        $bus = Bus::whereHas('students', function ($query) use ($student) {
            $query->where('student_id', $student->id);
        })->where('status', 'aktif')->first();
        if (!$bus) {
            return $this->responseError(
                'Anda tidak ditugaskan ke bus aktif manapun',
                403
            );
        }

        //Find halte terdekat dari GPS student
        $haltes = Halte::get();
        if ($haltes->isEmpty()) {
            return $this->responseError('Tidak ada halte yang terdaftar', 500);
        }

        // Calculate distance ke semua halte dan cari yang terdekat
        $nearestHalte = null;
        $nearestDistance = PHP_INT_MAX;
        foreach ($haltes as $halte) {
            $distance = Attendance::calculateDistance(
                $data['latitude'],
                $data['longitude'],
                $halte->latitude,
                $halte->longitude
            );
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestHalte = $halte;
            }
        }

        // validate Halte terdekat harus < 100m (jika tidak, siswa tidak di sekitar halte manapun)
        if ($nearestDistance > 100) {
            return $this->responseError(
                "Anda tidak berada di sekitar halte manapun. Halte terdekat: {$nearestDistance}m (diperlukan <100m)",
                400
            );
        }
        $halte = $nearestHalte;

        //Generate QR Code dengan data check-in, include random identifier
        $qrId = (string) Str::uuid();
        $qrData = json_encode([
            'id' => $qrId,
            'student_id' => $student->id,
            'bus_id' => $bus->id,
            'halte_id' => $halte->id,
            'tanggal' => $today,
            'latitude_naik' => $data['latitude'],
            'longitude_naik' => $data['longitude'],
        ]);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrData);

        //Return response dengan info QR (halte_id implicit)
        return $this->responseSuccess([
            'qr_id' => $qrId,
            'qr_code_url' => $qrCodeUrl,
            'qr_data' => json_decode($qrData, true),
            'bus_id' => $bus->id,
            'bus_code' => $bus->kode_bus,
            'halte_id' => $halte->id,
            'distance_to_halte' => $nearestDistance,
            'halte_info' => [
                'id' => $halte->id,
                'nama_halte' => $halte->nama_halte,
                'latitude' => $halte->latitude,
                'longitude' => $halte->longitude,
            ],
            'expires_at' => now()->endOfDay()->toIso8601String(),
            'message' => "QR Code valid hingga {$today} 23:59:59",
        ], 'QR Code berhasil dibuat');
    }

    //get bus yang ditugaskan untuk siswa
    public function myBus(Request $request) {
        $user = $request->user();
        $student = $user->student;
        $buses = $student->buses()->with('routes')->get();
        if ($buses->isEmpty()) {
            return $this->responseSuccess(null, 'Siswa belum ditugaskan ke bus manapun');
        }
        // Ambil bus pertama (siswa hanya punya 1 bus aktif)
        $bus = $buses->first();
        $data = [
            'bus_id'            => $bus->id,   // FE pakai bus_id untuk getRouteByBus
            'id'                => $bus->id,
            'kode_bus'          => $bus->kode_bus,
            'plat_nomor'        => $bus->plat_nomor,
            'status'            => $bus->status,
            'assigned_halte_id' => $bus->pivot->halte_id ?? null,
            'routes'            => $bus->routes->map(function ($route) {
                return [
                    'id'   => $route->id,
                    'name' => $route->nama_rute,
                ];
            })->values(),
        ];
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //Get real-time tracking bus siswa
    public function myBusTracking(Request $request) {
        $user = $request->user();
        $student = $user->student;
        $buses = $student->buses()->get();
        if ($buses->isEmpty()) {
            return $this->responseSuccess([], 'Siswa belum ditugaskan ke bus');
        }
        $busIds = $buses->pluck('id')->toArray();

        // Get latest GPS untuk setiap bus
        $subquery = GpsTrack::selectRaw('bus_id, MAX(recorded_at) as latest_time')->whereIn('bus_id', $busIds)->groupBy('bus_id');
        $latestGps = GpsTrack::joinSub($subquery, 'latest', function ($join) {
            $join->on('gps_tracks.bus_id', '=', 'latest.bus_id')
                ->on('gps_tracks.recorded_at', '=', 'latest.latest_time');
        })->with('bus')->get();

        //enrichment dengan GPS status dari bus_driver
        $trackingData = $latestGps->map(function ($track) {
            $busDriver = BusDriver::where('bus_id', $track->bus_id)->where(function ($q) {$q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', now()->toDateString());})->first();
            $gpsStatus = $busDriver ? $busDriver->gps_status : 'off';
            $isGpsActive = $gpsStatus === 'on' && $track->latitude && $track->longitude;
            return [
                'bus_id' => $track->bus_id,
                'bus_code' => $track->bus->kode_bus,
                'bus_plate' => $track->bus->plat_nomor,
                'gps_status' => $gpsStatus,
                'is_active' => $isGpsActive,
                'position' => $isGpsActive ? [
                    'latitude' => (float)$track->latitude,
                    'longitude' => (float)$track->longitude,
                    'speed' => (int)$track->speed,
                    'recorded_at' => $track->recorded_at,
                ] : null,
            ];
        });
        return $this->responseSuccess($trackingData, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get data GPS terbaru untuk bus yang ditugaskan kepada siswa
    public function getBusTracking(Request $request) {
        $user = $request->user();
        $student = $user->student;
        if (!$student) {
            return $this->responseNotFound('Student not found.');
        }
        $bus = $student->buses()->first();
        if (!$bus) {
            return $this->responseError('No bus assigned to the student.', null, 404);
        }
        $gpsTrack = GpsTrack::where('bus_id', $bus->id)->orderBy('recorded_at', 'desc')->first();
        if (!$gpsTrack) {
            return $this->responseError('No GPS data available for the assigned bus.', null, 404);
        }
        return $this->responseSuccess([
            'bus_id' => $bus->id,
            'bus_code' => $bus->kode_bus,
            'bus_plate' => $bus->plat_nomor,
            'position' => [
                'latitude' => (float)$gpsTrack->latitude,
                'longitude' => (float)$gpsTrack->longitude,
                'speed' => (float)($gpsTrack->speed ?? 0),
                'recorded_at' => $gpsTrack->recorded_at,
            ],
        ], 'GPS tracking data retrieved successfully');
    }
}