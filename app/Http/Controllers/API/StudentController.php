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

    //Get semua siswa (dengan info bus & rute)
    public function index(Request $request) {
        $paginator = $this->studentService->getAllStudents(15);
        $paginator->getCollection()->transform(fn($s) => $this->formatStudentWithBus($s));
        return $this->responsePaginated($paginator, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // detail siswa
    public function show(Request $request, $id) {
        $student = $this->studentService->getStudentById($id);
        return $this->responseSuccess($this->formatStudentWithBus($student), AppMessages::SUCCESS_DATA_RETRIEVED);
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

    //Get daftar siswa pending approval (dengan info bus & rute jika sudah ada)
    public function pending(Request $request) {
        $paginator = $this->studentService->getPendingStudents(15);
        $paginator->getCollection()->transform(fn($s) => $this->formatStudentWithBus($s));
        return $this->responsePaginated($paginator, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Approve siswa
    public function approve(Request $request, $id) {
        $result = $this->studentService->approveStudent($id);
        if (!$result['success']) {
            return $this->responseConflict($result['error']);
        }
        // Return format konsisten dengan index/pending agar Flutter bisa parse students.id
        return $this->responseSuccess(
            $this->formatStudentWithBus($result['student']),
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

        // Cek apakah siswa sedang dalam perjalanan (sudah check-in tapi belum checkout)
        // HANYA block jika status 'checked_in' (sudah naik bus) — jangan block 'pending'
        // karena 'pending' adalah QR yang belum discan driver dan harus bisa di-reuse
        $activeTrip = Attendance::where('student_id', $student->id)
            ->where('tanggal', $today)
            ->where('status', 'checked_in')
            ->whereNotNull('waktu_naik')
            ->whereNull('waktu_turun')
            ->first();
        if ($activeTrip) {
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

        // Cari halte yang ada di rute bus siswa ini — bukan semua halte
        // agar siswa tidak bisa generate QR di halte bus lain yang lebih dekat
        $busRoute = $bus->routes()->with('haltes')->first();
        $haltes = $busRoute ? $busRoute->haltes : collect();

        // Fallback 1: jika rute belum diset, gunakan halte yang di-assign ke siswa
        if ($haltes->isEmpty()) {
            $studentBusAssign = \App\Models\StudentBus::where('student_id', $student->id)
                ->where('bus_id', $bus->id)
                ->with('halte')
                ->first();
            if ($studentBusAssign && $studentBusAssign->halte) {
                $haltes = collect([$studentBusAssign->halte]);
            }
        }

        if ($haltes->isEmpty()) {
            return $this->responseError(
                'Rute bus belum diatur oleh admin. Hubungi admin sekolah.',
                500
            );
        }

        // Hitung jarak ke setiap halte di rute bus siswa
        $nearestHalte    = null;
        $nearestDistance = PHP_INT_MAX;
        foreach ($haltes as $h) {
            $distance = Attendance::calculateDistance(
                $data['latitude'],
                $data['longitude'],
                $h->latitude,
                $h->longitude
            );
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestHalte    = $h;
            }
        }

        // Cek apakah bus sudah sangat dekat ke siswa (bus datang menjemput)
        // Ini sebagai fallback jika siswa sedikit meleset dari titik halte
        $latestGps = GpsTrack::where('bus_id', $bus->id)
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->orderBy('recorded_at', 'desc')
            ->first();

        $busIsNear      = false;
        $distanceToBus  = null;
        if ($latestGps) {
            $distanceToBus = Attendance::calculateDistance(
                $data['latitude'],
                $data['longitude'],
                $latestGps->latitude,
                $latestGps->longitude
            );
            // Bus dalam radius 75m dari posisi siswa → boleh generate QR
            $busIsNear = $distanceToBus <= 75;
        }

        // Validasi: siswa harus dekat halte ATAU bus sudah tiba mendekati siswa
        $maxDistance = env('APP_ENV') === 'production' ? 100 : 999999;
        if ($nearestDistance > $maxDistance && !$busIsNear) {
            $msg = "Kamu belum berada di dekat halte. "
                 . "Halte terdekat: {$nearestDistance}m (diperlukan <{$maxDistance}m).";
            if ($distanceToBus !== null) {
                $msg .= " Jarak ke bus: {$distanceToBus}m.";
            }
            $msg .= " Tunggu di halte atau tunggu bus mendekati kamu.";
            return $this->responseError($msg, 400);
        }

        // Tentukan halte yang dipakai untuk check-in
        // Jika lolos via bus proximity (bus dekat tapi siswa belum tepat di halte),
        // gunakan halte yang di-assign ke siswa — bukan nearest halte yang mungkin beda
        if ($nearestDistance > $maxDistance && $busIsNear) {
            $assignedHalte = \App\Models\StudentBus::where('student_id', $student->id)
                ->where('bus_id', $bus->id)
                ->with('halte')
                ->first()?->halte;
            $halte = $assignedHalte ?? $nearestHalte;
        } else {
            $halte = $nearestHalte;
        }

        // Cek apakah sudah ada attendance hari ini dengan qr_id (belum discan / belum naik)
        // Jika ada, reuse qr_id yang sama agar QR tetap valid setelah refresh
        $existingPending = Attendance::where('student_id', $student->id)
            ->where('tanggal', $today)
            ->whereNull('waktu_naik')
            ->whereNotNull('qr_id')
            ->first();

        if ($existingPending) {
            // Reuse qr_id yang sudah ada — update halte & posisi jika berbeda
            $existingPending->update([
                'bus_id'       => $bus->id,
                'halte_naik_id' => $halte->id,
                'qr_expires_at' => now()->endOfDay(),
            ]);
            $qrId = $existingPending->qr_id;
        } else {
            // Generate QR baru dan simpan ke tabel attendance (status pending/belum naik)
            $qrId = (string) Str::uuid();
            Attendance::create([
                'qr_id'         => $qrId,
                'student_id'    => $student->id,
                'bus_id'        => $bus->id,
                'halte_naik_id' => $halte->id,
                'tanggal'       => $today,
                'waktu_naik'    => null,  // belum naik, menunggu scan driver
                'lat_naik'      => $data['latitude'],
                'lng_naik'      => $data['longitude'],
                'status'        => 'pending',
                'qr_expires_at' => now()->endOfDay(),
            ]);
        }

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
        $user    = $request->user();
        $student = $user->student;
        $buses   = $student->buses()->with(['routes.haltes', 'routes.polylines'])->get();

        if ($buses->isEmpty()) {
            return $this->responseSuccess(null, 'Siswa belum ditugaskan ke bus manapun');
        }

        // Ambil bus pertama (siswa hanya punya 1 bus aktif)
        $bus   = $buses->first();
        $today = now()->toDateString();

        // Ambil info driver aktif & gps_status agar FE bisa tampilkan status bus
        $busDriver = \App\Models\BusDriver::where('bus_id', $bus->id)
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $today);
            })
            ->with('driver:id,user_id', 'driver.user:id,name')
            ->orderByRaw("CASE WHEN gps_status = 'on' THEN 0 ELSE 1 END")
            ->orderBy('last_gps_update', 'desc')
            ->first();

        $gpsStatus  = $busDriver?->gps_status ?? 'off';
        $driverName = $busDriver?->driver?->user?->name ?? null;

        $data = [
            'bus_id'            => $bus->id,
            'id'                => $bus->id,
            'kode_bus'          => $bus->kode_bus,
            'plat_nomor'        => $bus->plat_nomor,
            'status'            => $bus->status,
            'gps_status'        => $gpsStatus,
            'driver_name'       => $driverName,
            'assigned_halte_id' => $bus->pivot->halte_id ?? null,
            'routes'            => $bus->routes->map(function ($route) use ($bus) {
                return [
                    'id'        => $route->id,
                    'bus_id'    => $bus->id,
                    'nama_rute' => $route->nama_rute,
                    'haltes'    => $route->haltes->map(fn($h) => [
                        'id'         => $h->id,
                        'nama_halte' => $h->nama_halte,
                        'latitude'   => (float) $h->latitude,
                        'longitude'  => (float) $h->longitude,
                        'urutan'     => $h->pivot->urutan ?? 0,
                    ])->sortBy('urutan')->values(),
                    'polyline'  => $route->polylines->map(fn($p) => [
                        'latitude'  => (float) $p->latitude,
                        'longitude' => (float) $p->longitude,
                        'urutan'    => $p->urutan,
                    ])->sortBy('urutan')->values(),
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
        $user    = $request->user();
        $student = $user->student;
        if (!$student) return $this->responseNotFound('Student not found.');

        $bus = $student->buses()->with(['routes.haltes', 'routes.polylines'])->first();
        if (!$bus) return $this->responseError('No bus assigned to the student.', null, 404);

        // Cek status GPS dari BusDriver (sumber kebenaran utama)
        // Jika driver matikan GPS, gps_status = 'off' dan kita TIDAK tampilkan posisi
        $today = now()->toDateString();
        $busDriver = \App\Models\BusDriver::where('bus_id', $bus->id)
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $today);
            })
            ->with('driver:id,user_id,no_hp', 'driver.user:id,name')
            ->orderByRaw("CASE WHEN gps_status = 'on' THEN 0 ELSE 1 END")
            ->orderBy('last_gps_update', 'desc')
            ->first();

        $gpsStatusOn = $busDriver && $busDriver->gps_status === 'on';

        // Ambil posisi GPS terakhir HANYA jika GPS memang sedang aktif
        $gpsTrack = null;
        if ($gpsStatusOn) {
            // Filter hari ini agar tidak pakai posisi dari hari kemarin
            $gpsTrack = GpsTrack::where('bus_id', $bus->id)
                ->whereDate('recorded_at', $today)
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                ->orderBy('recorded_at', 'desc')
                ->first();
        }

        // Halte penjemputan siswa ini
        $studentBus  = \App\Models\StudentBus::where('student_id', $student->id)
            ->where('bus_id', $bus->id)->with('halte')->first();
        $myHalte     = $studentBus?->halte;

        // Nama driver aktif
        $driverName = $busDriver?->driver?->user?->name ?? null;

        // Format rute untuk peta (polyline + titik halte)
        $routes = $bus->routes->map(function ($route) {
            return [
                'id'        => $route->id,
                'bus_id'    => $route->bus_id ?? 0,
                'nama_rute' => $route->nama_rute,
                'haltes'    => $route->haltes->map(fn($h) => [
                    'id'          => $h->id,
                    'nama_halte'  => $h->nama_halte,
                    'latitude'    => (float) $h->latitude,
                    'longitude'   => (float) $h->longitude,
                    'urutan'      => $h->pivot->urutan ?? 0,
                ])->sortBy('urutan')->values(),
                'polyline'  => $route->polylines->map(fn($p) => [
                    'latitude'  => (float) $p->latitude,
                    'longitude' => (float) $p->longitude,
                    'urutan'    => $p->urutan,
                ])->values(),
            ];
        })->values();

        return $this->responseSuccess([
            'bus_id'      => $bus->id,
            'bus_code'    => $bus->kode_bus,
            'bus_plate'   => $bus->plat_nomor,
            // gps_active HANYA true jika driver benar-benar sedang aktif (gps_status = 'on')
            'gps_active'  => $gpsStatusOn && $gpsTrack !== null,
            // Posisi null jika GPS off — Flutter tidak akan tampilkan marker
            'position'    => ($gpsStatusOn && $gpsTrack) ? [
                'latitude'    => (float) $gpsTrack->latitude,
                'longitude'   => (float) $gpsTrack->longitude,
                'speed'       => (float) ($gpsTrack->speed ?? 0),
                'recorded_at' => $gpsTrack->recorded_at,
            ] : null,
            // Info driver aktif untuk ditampilkan ke siswa
            'driver_name' => $driverName,
            // Halte penjemputan milik siswa ini
            'my_halte'  => $myHalte ? [
                'id'         => $myHalte->id,
                'nama_halte' => $myHalte->nama_halte,
                'latitude'   => (float) $myHalte->latitude,
                'longitude'  => (float) $myHalte->longitude,
            ] : null,
            // Rute lengkap untuk gambar polyline & halte di peta
            'routes'      => $routes,
        ], 'GPS tracking data retrieved successfully');
    }

    /**
     * Format data siswa beserta info bus, rute, dan halte.
     * Dipakai agar Flutter side bisa render QR card dengan info rute lengkap.
     */
    private function formatStudentWithBus($student): array {
        $bus   = $student->buses()->where('status', 'aktif')->with('routes')->first();
        $route = $bus?->routes->first();

        // Ambil halte dari pivot student_bus
        $studentBus = \App\Models\StudentBus::where('student_id', $student->id)
            ->when($bus, fn($q) => $q->where('bus_id', $bus->id))
            ->with('halte')
            ->first();
        $halte = $studentBus?->halte;

        return [
            'id'              => $student->id,
            'user_id'         => $student->user_id,
            'nis'             => $student->nis,
            'sekolah'         => $student->sekolah,
            'kelas'           => $student->kelas,
            'alamat'          => $student->alamat,
            'no_hp'           => $student->no_hp,
            'approval_status' => $student->approval_status,
            'rejection_reason'=> $student->rejection_reason ?? null,
            'user'            => $student->user ? [
                'id'    => $student->user->id,
                'name'  => $student->user->name,
                'email' => $student->user->email,
                'role'  => $student->user->role,
            ] : null,
            // Info bus & rute untuk QR card
            'bus'   => $bus ? [
                'id'        => $bus->id,
                'kode_bus'  => $bus->kode_bus,
                'plat_nomor'=> $bus->plat_nomor,
                'routes'    => $route ? [['id' => $route->id, 'nama_rute' => $route->nama_rute]] : [],
            ] : null,
            'halte' => $halte ? [
                'id'          => $halte->id,
                'nama_halte'  => $halte->nama_halte,
            ] : null,
            'bus_id'    => $bus?->id ?? 0,
            'halte_id'  => $halte?->id ?? 0,
            'kode_bus'  => $bus?->kode_bus ?? '',
            'nama_rute' => $route?->nama_rute ?? '',
            'nama_halte'=> $halte?->nama_halte ?? '',
        ];
    }
}