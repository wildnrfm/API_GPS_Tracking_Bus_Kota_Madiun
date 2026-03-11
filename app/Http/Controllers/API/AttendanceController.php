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

        // validate Student exists dan approved
        $student = Student::findOrFail($data['student_id']);
        if ($student->approval_status !== 'approved') {
            return $this->responseForbidden('Siswa belum di-approve');
        }

        // Validate Tanggal dari QR = hari ini
        if ($data['tanggal'] !== now()->toDateString()) {
            return $this->responseError('QR Code sudah expired atau tanggal tidak sesuai', 400);
        }

        // Validate Bus status aktif
        $bus = Bus::findOrFail($data['bus_id']);
        if ($bus->status !== 'aktif') {
            return $this->responseForbidden('Bus tidak dalam status operasional');
        }

        // Validate Siswa di-assign ke bus ini
        $studentBusAssignment = StudentBus::where('student_id', $student->id)->where('bus_id', $bus->id)->first();
        if (!$studentBusAssignment) {
            return $this->responseForbidden('Siswa tidak ditugaskan ke bus ini');
        }

        //Validate Jarak driver dengan siswa < 100m (gunakan halte sebagai proksi)
        $halte = Halte::findOrFail($data['halte_id']);
        $distance = Attendance::calculateDistance(
            $data['latitude'],
            $data['longitude'],
            $halte->latitude,
            $halte->longitude
        );
        if ($distance > 100) {
            return $this->responseError("Jarak siswa dengan halte terlalu jauh ({$distance}m, diperlukan <100m)", 400);
        }

        // pastikan tidak ada sesi terbuka untuk siswa pada tanggal ini (belum checkout)
        $openAttendance = Attendance::where('student_id', $student->id)->where('tanggal', $data['tanggal'])->whereNull('waktu_turun')->first();
        if ($openAttendance && $openAttendance->qr_id !== $data['qr_id']) {
            return $this->responseConflict(
                ['attendance_id' => $openAttendance->id],
                'Siswa sudah check-in dan belum checkout. Silakan checkout terlebih dahulu.'
            );
        }

        // Cek apakah qr_id sudah ada di tabel
        $existingAttendance = Attendance::where('qr_id', $data['qr_id'])->first();
        if ($existingAttendance) {

            // jika sudah ada record, pastikan belum dipakai untuk check-in / checkout
            if ($existingAttendance->waktu_turun) {
                return $this->responseConflict([], 'QR code sudah dipakai dan tidak bisa digunakan lagi.');
            }
            if ($existingAttendance->waktu_naik) {

                // record sudah check-in tapi belum checkout -> konflik
                return $this->responseConflict(
                    ['attendance_id' => $existingAttendance->id],
                    'Siswa sudah check-in dan belum checkout. Silakan checkout terlebih dahulu.'
                );
            }

            // record ada tetapi belum memiliki waktu naik, artinya siswa baru digenerate QR, sistem akan perbarui dengan data scan
            $attendance = $existingAttendance;
            $attendance->update([
                'bus_id' => $bus->id,
                'halte_naik_id' => $data['halte_id'],
                'waktu_naik' => now(),
                'lat_naik' => $data['latitude'],
                'lng_naik' => $data['longitude'],
                'status' => 'checked_in',
                'qr_expires_at' => now()->endOfDay(),
            ]);
        } else {

            // tidak ada record sama sekali, buat baru
            $attendance = Attendance::create([
                'qr_id' => $data['qr_id'],
                'student_id' => $student->id,
                'bus_id' => $bus->id,
                'halte_naik_id' => $data['halte_id'],
                'tanggal' => $data['tanggal'],
                'waktu_naik' => now(),
                'lat_naik' => $data['latitude'],
                'lng_naik' => $data['longitude'],
                'status' => 'checked_in',
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

        return $this->responseCreated([
            'attendance_id' => $attendance->id,
            'student_id' => $attendance->student_id,
            'student_name' => $student->user->name,
            'student_nis' => $student->nis,
            'bus_id' => $attendance->bus_id,
            'bus_code' => $bus->kode_bus,
            'halte_naik' => $halte->nama_halte,
            'waktu_naik' => $attendance->waktu_naik,
            'distance_valid' => true,
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

        // validate Attendance ini sudah check-in (waktu_naik ada)
        if (!$attendance->waktu_naik) {
            return $this->responseError('Attendance belum check-in, tidak bisa checkout', 400);
        }

        //validate belum checkout (waktu_turun null)
        if ($attendance->waktu_turun) {
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
        $this->authorizeAdmin($request);
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
        $this->authorizeAdmin($request);
        $attendance = Attendance::with('student.user', 'bus')->findOrFail($id);
        return $this->responseSuccess($attendance, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Get Absensi hari ini berdasarkan bus
    public function byBusToday(Request $request, $busId) {
        $this->authorizeAdmin($request);
        $bus = Bus::findOrFail($busId);
        $attendance = Attendance::where('bus_id', $busId)->whereDate('tanggal', now()->toDateString())->with('student.user')->orderBy('waktu_naik', 'asc')->get();
        return $this->responseSuccess([
            'bus_id' => $busId,
            'bus_code' => $bus->kode_bus,
            'date' => now()->toDateString(),
            'total_scanned' => $attendance->count(),
            'data' => $attendance
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

    // delete record absensi
    public function destroy(Request $request, $id) {
        $this->authorizeAdmin($request);
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}
