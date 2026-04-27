<?php

namespace App\Http\Controllers\API;

use App\Constants\AppMessages;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Bus;
use App\Models\Halte;
use App\Models\StudentBus;
use App\Models\DailyReport;
use App\Jobs\LogActivityAsync;
use App\Services\OfflineDataService;
use Illuminate\Http\Request;

// CRUD absensi siswa (check-in, check-out, records)
class AttendanceController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    // Driver scan siswa untuk check-in (dari QR code siswa)
    public function scan(Request $request) {
        $driver = $request->user();
        $data = $request->validate([
            'qr_id' => 'required|string', // random identifier dari QR
            'student_id' => 'required|integer|exists:students,id',
            'bus_id' => 'required|integer|exists:buses,id',
            'halte_id' => 'required|integer|exists:haltes,id',
            'tanggal' => 'required|date_format:Y-m-d',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'qr_id.required' => 'Identifier QR wajib disertakan',
            'qr_id.string' => 'Identifier QR tidak valid',
            'student_id.required' => 'ID siswa wajib diisi',
            'student_id.integer' => 'ID siswa harus berupa angka',
            'student_id.exists' => 'Siswa tidak ditemukan',
            'bus_id.required' => 'ID bus wajib diisi',
            'bus_id.integer' => 'ID bus harus berupa angka',
            'bus_id.exists' => 'Bus tidak ditemukan',
            'halte_id.required' => 'ID halte wajib diisi',
            'halte_id.integer' => 'ID halte harus berupa angka',
            'halte_id.exists' => 'Halte tidak ditemukan',
            'tanggal.required' => 'Tanggal wajib diisi',
            'tanggal.date_format' => 'Format tanggal harus YYYY-MM-DD',
            'latitude.required' => 'Latitude (lintang) wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus berada antara -90 sampai 90',
            'longitude.required' => 'Longitude (bujur) wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus berada antara -180 sampai 180',
        ]);

        // ── VALIDASI UTAMA: bus driver harus = bus siswa ─────────────────────────
        // Backend TIDAK percaya bus_id dari request/QR karena bisa dimanipulasi.
        // Backend cari sendiri bus milik driver yang login, lalu bandingkan
        // dengan bus yang terdaftar untuk siswa.
        // Dengan cara ini, driver Bus A tidak bisa scan siswa Bus B meski QR-nya valid.

        $student = Student::findOrFail($data['student_id']);
        if ($student->approval_status !== 'approved') {
            return $this->responseForbidden('Siswa belum di-approve');
        }

        // 1. Cari bus milik driver yang sedang login (bukan dari request)
        $driverProfile = $driver->driver;
        $today = now()->toDateString();
        $driverBusAssignment = $driverProfile
            ? \App\Models\BusDriver::where('driver_id', $driverProfile->id)
                ->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_selesai')
                      ->orWhere('tanggal_selesai', '>=', $today);
                })
                ->with('bus.routes')
                ->first()
            : null;

        if (!$driverBusAssignment || !$driverBusAssignment->bus) {
            return $this->responseError('Driver tidak memiliki bus yang aktif hari ini.', 403);
        }

        // Gunakan bus milik driver (bukan bus_id dari QR)
        $bus = $driverBusAssignment->bus;

        // 2. Cek apakah siswa terdaftar di bus driver ini
        $studentBusAssignment = StudentBus::where('student_id', $student->id)
            ->where('bus_id', $bus->id)
            ->first();

        if (!$studentBusAssignment) {
            // Ambil info bus yang seharusnya untuk pesan error yang informatif
            $correctAssignment = StudentBus::where('student_id', $student->id)
                ->with(['bus.routes'])
                ->first();

            $correctBusInfo = null;
            if ($correctAssignment?->bus) {
                $correctRoute = $correctAssignment->bus->routes->first();
                $correctBusInfo = [
                    'bus_id'    => $correctAssignment->bus->id,
                    'kode_bus'  => $correctAssignment->bus->kode_bus,
                    'nama_rute' => $correctRoute?->nama_rute ?? '-',
                ];
            }

            return response()->json([
                'success'      => false,
                'message'      => 'Rute tidak sesuai. Siswa ini tidak terdaftar di bus kamu.',
                'error_type'   => 'route_mismatch',
                'student_name' => $student->user->name,
                'student_nis'  => $student->nis,
                'scanned_bus'  => [
                    'bus_id'    => $bus->id,
                    'kode_bus'  => $bus->kode_bus,
                    'nama_rute' => $bus->routes->first()->nama_rute ?? '-',
                ],
                'correct_bus'  => $correctBusInfo,
            ], 403);
        }

        // Validate Tanggal dari QR = hari ini
        if ($data['tanggal'] !== $today) {
            return $this->responseError('QR Code sudah expired atau tanggal tidak sesuai', 400);
        }

        // Validate Bus masih aktif
        if ($bus->status !== 'aktif') {
            return $this->responseForbidden('Bus tidak dalam status operasional');
        }

        //Validate Jarak driver dengan siswa < 100m (gunakan halte sebagai proksi)
        $halte = Halte::findOrFail($data['halte_id']);
        $distance = Attendance::calculateDistance(
            $data['latitude'],
            $data['longitude'],
            $halte->latitude,
            $halte->longitude
        );
        // Development: batas dinonaktifkan agar bisa test tanpa GPS asli
        // Production: ganti kembali ke 100
        $maxDistance = env('APP_ENV') === 'production' ? 100 : 999999;
        if ($distance > $maxDistance) {
            return $this->responseError("Jarak siswa dengan halte terlalu jauh ({$distance}m, diperlukan <{$maxDistance}m)", 400);
        }

        // Cari record attendance berdasarkan qr_id (dibuat saat siswa generate QR)
        $existingAttendance = Attendance::where('qr_id', $data['qr_id'])->first();

        if ($existingAttendance) {
            // QR sudah dipakai checkout — tolak
            if ($existingAttendance->waktu_turun) {
                return $this->responseConflict([], 'QR code sudah dipakai checkout dan tidak bisa digunakan lagi.');
            }
            // QR sudah di-scan check-in — cek apakah ini scan ulang dari driver yang sama (idempoten)
            if ($existingAttendance->waktu_naik) {
                return $this->responseConflict(
                    ['attendance_id' => $existingAttendance->id],
                    'Siswa sudah check-in dan belum checkout. Silakan checkout terlebih dahulu.'
                );
            }
            // QR dalam status pending (dibuat saat generate) — update dengan data scan driver
            $attendance = $existingAttendance;
            $attendance->update([
                'bus_id'        => $bus->id,
                'halte_naik_id' => $data['halte_id'],
                'waktu_naik'    => now(),
                'lat_naik'      => $data['latitude'],
                'lng_naik'      => $data['longitude'],
                'status'        => 'checked_in',
                'qr_expires_at' => now()->endOfDay(),
            ]);
        } else {
            // qr_id tidak dikenal — cek apakah siswa ini sudah punya sesi terbuka hari ini
            $openAttendance = Attendance::where('student_id', $student->id)
                ->where('tanggal', $data['tanggal'])
                ->whereNull('waktu_turun')
                ->whereNotNull('waktu_naik')
                ->first();
            if ($openAttendance) {
                return $this->responseConflict(
                    ['attendance_id' => $openAttendance->id],
                    'Siswa sudah check-in dan belum checkout. Silakan checkout terlebih dahulu.'
                );
            }
            // QR tidak dikenal (mungkin QR lama sebelum sistem baru) — buat record baru
            $attendance = Attendance::create([
                'qr_id'         => $data['qr_id'],
                'student_id'    => $student->id,
                'bus_id'        => $bus->id,
                'halte_naik_id' => $data['halte_id'],
                'tanggal'       => $data['tanggal'],
                'waktu_naik'    => now(),
                'lat_naik'      => $data['latitude'],
                'lng_naik'      => $data['longitude'],
                'status'        => 'checked_in',
                'qr_expires_at' => now()->endOfDay(),
            ]);
        }

        // Log activity
        LogActivityAsync::dispatch('attendance_check_in', $driver->id, [
            'model' => 'Attendance',
            'model_id' => $attendance->id,
            'description' => 'Driver ' . $driver->name . ' scan siswa ' . $student->nis . ' di halte ' . $halte->nama_halte,
            'status' => 'success',
        ]);

        //Sync offline data
        OfflineDataService::logDataSync(
            'attendance',
            $attendance->id,
            $request->input('device_id', 'unknown'),
            $attendance->toArray(),
            'synced'
        );

        //Update atau create daily report
        $dailyReport = DailyReport::firstOrCreate(
            ['bus_id' => $bus->id, 'tanggal' => $data['tanggal']],
            ['status' => 'draft', 'km_awal' => 0, 'km_akhir' => 0, 'bahan_bakar' => '-', 'total_penumpang' => 1]
        );

        // Jika entri sudah ada, pastikan total_penumpang tidak diubah
        if (!$dailyReport->wasRecentlyCreated) {
            $dailyReport->increment('total_penumpang');
        }

        // Ambil info rute bus
        $route = $bus->routes->first();

        return $this->responseCreated([
            'attendance_id' => $attendance->id,
            'student_id'    => $attendance->student_id,
            'student_name'  => $student->user->name,
            'student_nis'   => $student->nis,
            'bus_id'        => $attendance->bus_id,
            'bus_code'      => $bus->kode_bus,
            'halte_naik'    => $halte->nama_halte,
            'waktu_naik'    => $attendance->waktu_naik,
            'distance_valid' => true,
            'route_info'    => [
                'route_id'   => $route?->id,
                'nama_rute'  => $route?->nama_rute ?? '-',
                'kode_bus'   => $bus->kode_bus,
                'plat_nomor' => $bus->plat_nomor,
            ],
        ], 'Siswa berhasil check-in');
    }

    // Driver scan siswa untuk check-out
    public function checkOut(Request $request) {
        $driver = $request->user();
        $data = $request->validate([
            'qr_id' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ], [
            'qr_id.required' => 'Identifier QR wajib disertakan',
            'qr_id.string' => 'Identifier QR tidak valid',
            'latitude.required' => 'Latitude (lintang) wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'latitude.between' => 'Latitude harus berada antara -90 sampai 90',
            'longitude.required' => 'Longitude (bujur) wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
            'longitude.between' => 'Longitude harus berada antara -180 sampai 180',
        ]);

        //Get attendance record by qr_id
        $attendance = Attendance::where('qr_id', $data['qr_id'])->first();
        if (!$attendance) {
            return $this->responseError('Attendance tidak ditemukan', 404);
        }

        // PERBAIKAN: validasi lengkap sebelum checkout
        // Cek status explicitly - jangan hanya cek waktu_naik
        if ($attendance->status === 'pending' || !$attendance->waktu_naik) {
            return $this->responseError(
                'Siswa belum naik bus (QR belum discan saat naik). Tidak bisa checkout.',
                400
            );
        }

        if ($attendance->status === 'checked_out' || $attendance->waktu_turun) {
            return $this->responseConflict([], 'Siswa sudah checkout');
        }

        // validate tanggal checkout = hari ini
        if ($attendance->tanggal->toDateString() !== now()->toDateString()) {
            return $this->responseError('Hanya bisa checkout pada hari yang sama dengan check-in', 400);
        }

        // Update attendance
        $attendance->update([
            'waktu_turun' => now(),
            'lat_turun' => $data['latitude'],
            'lng_turun' => $data['longitude'],
            'status' => 'checked_out',
            'qr_expires_at' => now(), // QR expires immediately after checkout
        ]);

        // Log activity
        LogActivityAsync::dispatch('attendance_check_out', $driver->id, [
            'model' => 'Attendance',
            'model_id' => $attendance->id,
            'description' => 'Driver ' . $driver->name . ' scan siswa ' . $attendance->student->nis . ' untuk checkout',
            'status' => 'success',
        ]);

        // Sync offline data
        OfflineDataService::logDataSync(
            'attendance',
            $attendance->id,
            $request->input('device_id', 'unknown'),
            $attendance->toArray(),
            'synced'
        );

        return $this->responseSuccess([
            'attendance_id' => $attendance->id,
            'student_id' => $attendance->student_id,
            'student_name' => $attendance->student->user->name,
            'student_nis' => $attendance->student->nis,
            'bus_id' => $attendance->bus_id,
            'bus_code' => $attendance->bus->kode_bus,
            'waktu_naik' => $attendance->waktu_naik,
            'waktu_turun' => $attendance->waktu_turun,
            'status' => $attendance->status,
        ], 'Siswa berhasil checkout');
    }

    // get daftar absensi dengan filter
    public function index(Request $request) {
        $query = Attendance::with('student.user', 'bus');
        if ($request->has('bus_id')) {
            $query->where('bus_id', $request->input('bus_id'));
        }
        if ($request->has('date')) {
            $query->whereDate('tanggal', $request->input('date'));
        }
        if ($request->has('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }
        $attendance = $query->orderBy('waktu_naik', 'desc')->paginate(50);
        return $this->responsePaginated($attendance, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get detail absensi
    public function show(Request $request, $id) {
        $attendance = Attendance::with('student.user', 'bus')->findOrFail($id);
        return $this->responseSuccess($attendance, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Get Absensi hari ini berdasarkan bus
    public function byBusToday(Request $request, $busId) {
        $bus = Bus::findOrFail($busId);
        // PERBAIKAN BUG: tambah filter status agar record 'pending' tidak ikut tampil.
        // Record 'pending' = siswa sudah generate QR tapi belum discan driver.
        // Tanpa filter ini, siswa pending muncul di list "Di Bus" dengan tombol Checkout
        // padahal mereka belum naik sama sekali — driver bisa checkout siswa yang belum naik.
        $attendances = Attendance::where('bus_id', $busId)
            ->whereDate('tanggal', now()->toDateString())
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereNotNull('waktu_naik')
            ->with(['student.user', 'halteNaik'])
            ->orderBy('waktu_naik', 'asc')
            ->get();

        $data = $attendances->map(function ($a) {
            return [
                'attendance_id' => $a->id,
                'qr_id'         => $a->qr_id,
                'student_id'    => $a->student_id,
                'student_name'  => $a->student->user->name ?? '-',
                'student_nis'   => $a->student->nis ?? '-',
                'bus_code'      => $a->bus->kode_bus ?? '-',
                'halte_naik'    => $a->halteNaik->nama_halte ?? '-',
                'waktu_naik'    => $a->waktu_naik,
                'waktu_turun'   => $a->waktu_turun,
                'status'        => $a->status,
            ];
        });

        return $this->responseSuccess([
            'bus_id'        => $busId,
            'bus_code'      => $bus->kode_bus,
            'date'          => now()->toDateString(),
            'total_scanned' => $attendances->count(),
            'total_onboard' => $attendances->whereNull('waktu_turun')->count(),
            'data'          => $data,
        ], AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // get Absensi siswa hari ini
    public function studentTodayAttendance(Request $request, $studentId) {
        $user = $request->user();
        if ($user->role === 'siswa' && $user->student->id != $studentId) {
            return $this->responseForbidden(AppMessages::ERROR_FORBIDDEN);
        }
        $student = Student::findOrFail($studentId);
        $attendance = Attendance::where('student_id', $studentId)->whereDate('tanggal', now()->toDateString())->with('bus')->orderBy('waktu_naik', 'asc')->get();
        return $this->responseSuccess([
            'student_id' => $studentId,
            'student_name' => $student->user->name,
            'date' => now()->toDateString(),
            'data' => $attendance
        ], AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Absensi siswa yang sedang login hari ini — tanpa ID di URL (student route)
    public function myAttendanceToday(Request $request) {
        $user    = $request->user();
        $student = $user->student;
        if (!$student) {
            return $this->responseNotFound('Profil siswa tidak ditemukan');
        }
        // PERBAIKAN: urutkan agar record checked_in/checked_out (yang aktif)
        // selalu di akhir list → Flutter ambil list.last = status terbaru yang relevan.
        // Ini mencegah status 'pending' lama override status 'checked_in' baru.
        $attendances = Attendance::where('student_id', $student->id)
            ->whereDate('tanggal', now()->toDateString())
            ->with(['bus', 'halteNaik'])
            ->orderByRaw("CASE WHEN status IN ('checked_in','checked_out') THEN 1 ELSE 0 END ASC")
            ->orderBy('id', 'asc')
            ->get();

        $data = $attendances->map(function ($a) {
            return [
                'attendance_id' => $a->id,
                'qr_id'         => $a->qr_id,
                'bus_id'        => $a->bus_id,
                'bus_code'      => $a->bus?->kode_bus ?? '-',
                'halte_naik'    => $a->halteNaik?->nama_halte ?? '-',
                'waktu_naik'    => $a->waktu_naik,
                'waktu_turun'   => $a->waktu_turun,
                'status'        => $a->status,
            ];
        });

        return $this->responseSuccess([
            'student_id'   => $student->id,
            'student_name' => $user->name,
            'date'         => now()->toDateString(),
            'data'         => $data,
        ], AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // delete record absensi
    public function destroy(Request $request, $id) {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}