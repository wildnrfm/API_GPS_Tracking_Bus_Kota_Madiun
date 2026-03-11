<?php

namespace App\Http\Controllers\API;

use App\Models\DailyReport;
use App\Models\Bus;
use App\Models\BusDriver;
use App\Models\GpsTrack;
use App\Models\Attendance;
use App\Constants\AppMessages;
use Illuminate\Http\Request;

// laporan harian untuk setiap bus, admin only
class DailyReportController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    //create laporan harian baru
    public function store(Request $request) {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'tanggal' => 'required|date_format:Y-m-d',
            'km_awal' => 'required|numeric|min:0',
            'km_akhir' => 'required|numeric|min:0',
            'bahan_bakar' => 'required|string|max:50',
            'keterangan' => 'nullable|string|max:1000',
        ], [
            'bus_id.required' => 'ID bus harus diisi',
            'bus_id.exists' => 'Bus tidak ditemukan',
            'tanggal.required' => 'Tanggal harus diisi',
            'tanggal.date_format' => 'Format tanggal harus YYYY-MM-DD',
            'km_awal.required' => 'KM awal harus diisi',
            'km_akhir.required' => 'KM akhir harus diisi',
            'bahan_bakar.required' => 'Tipe bahan bakar harus diisi',
        ]);
        if ($data['km_akhir'] < $data['km_awal']) {
            return $this->responseError('KM akhir harus lebih besar atau sama dengan KM awal', 422);
        }
        $bus = Bus::findOrFail($data['bus_id']);
        $activeDriver = BusDriver::where('bus_id', $data['bus_id'])->where('tanggal_mulai', '<=', $data['tanggal'])
            ->where(function($q) use ($data) {$q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $data['tanggal']);})->with('driver.user')->first();
        $gpsTracks = GpsTrack::where('bus_id', $data['bus_id'])->whereDate('recorded_at', $data['tanggal'])->orderBy('recorded_at', 'asc')->get();
        $attendances = Attendance::where('bus_id', $data['bus_id'])->whereDate('tanggal', $data['tanggal'])->get();
        $distanceTraveled = $data['km_akhir'] - $data['km_awal'];
        $totalStudents = $attendances->count();
        $jamBerangkat = $gpsTracks->first()?->recorded_at ?? now();
        $jamTiba = $gpsTracks->last()?->recorded_at ?? now();
        $report = DailyReport::create([
            'bus_id' => $data['bus_id'],
            'tanggal' => $data['tanggal'],
            'km_awal' => $data['km_awal'],
            'km_akhir' => $data['km_akhir'],
            'jarak_tempuh' => $distanceTraveled,
            'bahan_bakar' => $data['bahan_bakar'],
            'bahan_bakar_dipakai' => 0,
            'jam_berangkat' => $jamBerangkat,
            'jam_tiba' => $jamTiba,
            'total_siswa' => $totalStudents,
            'keterangan' => $data['keterangan'] ?? null,
            'driver_id' => $activeDriver?->driver_id,
            'route_id' => $bus->route_id,
        ]);

        // Update jumlah_penumpang, penumpang_naik, penumpang_turun, dan nama_siswa dynamically
        $report->increment('jumlah_penumpang');
        $report->increment('penumpang_naik');
        $report->nama_siswa = $report->nama_siswa ? $report->nama_siswa . ',' . $studentId : $studentId;
        $report->save();

        return $this->responseCreated([
            'id' => $report->id,
            'bus_id' => $report->bus_id,
            'bus_code' => $bus->kode_bus,
            'bus_plate' => $bus->plat_nomor,
            'tanggal' => $report->tanggal,
            'jarak_tempuh' => $report->jarak_tempuh,
            'total_siswa' => $report->total_siswa,
            'driver_name' => $activeDriver?->driver->user->name,
            'route_name' => $bus->route->nama_rute ?? null,
        ], AppMessages::SUCCESS_CREATED);
    }

    // get daftar laporan harian dengan filter
    public function index(Request $request) {
        $this->authorizeAdmin($request);
        $query = DailyReport::with('bus', 'driver.user', 'route');
        if ($request->has('bus_id')) {
            $query->where('bus_id', $request->input('bus_id'));
        }
        if ($request->has('date_from')) {
            $query->whereDate('tanggal', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('tanggal', '<=', $request->input('date_to'));
        }
        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }
        $reports = $query->orderBy('tanggal', 'desc')->paginate($request->input('per_page', 15));
        return $this->responsePaginated($reports, AppMessages::SUCCESS_RETRIEVED);
    }

    //get detail laporan harian dengan data pendukung
    public function show(Request $request, $id) {
        $this->authorizeAdmin($request);
        $report = DailyReport::with('bus', 'driver.user', 'route')->findOrFail($id);
        $attendances = Attendance::where('bus_id', $report->bus_id)->whereDate('tanggal', $report->tanggal)->with('student.user')->get();
        $gpsCount = GpsTrack::where('bus_id', $report->bus_id)->whereDate('recorded_at', $report->tanggal)->count();
        return $this->responseSuccess([
            'id' => $report->id,
            'bus_id' => $report->bus_id,
            'bus_code' => $report->bus->kode_bus,
            'bus_plate' => $report->bus->plat_nomor,
            'tanggal' => $report->tanggal,
            'km_awal' => $report->km_awal,
            'km_akhir' => $report->km_akhir,
            'jarak_tempuh' => $report->jarak_tempuh,
            'bahan_bakar' => $report->bahan_bakar,
            'bahan_bakar_dipakai' => $report->bahan_bakar_dipakai,
            'jam_berangkat' => $report->jam_berangkat,
            'jam_tiba' => $report->jam_tiba,
            'total_siswa' => $report->total_siswa,
            'keterangan' => $report->keterangan,
            'driver' => [
                'id' => $report->driver->id ?? null,
                'name' => $report->driver?->user->name,
                'nik' => $report->driver?->nik,
            ],
            'route' => [
                'id' => $report->route->id ?? null,
                'nama_rute' => $report->route->nama_rute ?? null,
            ],
            'summary' => [
                'gps_tracks_recorded' => $gpsCount,
                'students_attended' => $report->total_siswa,
                'students_detail' => $attendances->map(function($att) {
                    return [
                        'student_id' => $att->student_id,
                        'student_name' => $att->student->user->name,
                        'student_nis' => $att->student->nis,
                        'waktu_naik' => $att->waktu_naik,
                    ];
                })
            ]
        ], AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //update laporan harian
    public function update(Request $request, $id) {
        $this->authorizeAdmin($request);
        $report = DailyReport::findOrFail($id);
        $data = $request->validate([
            'km_awal' => 'sometimes|numeric|min:0',
            'km_akhir' => 'sometimes|numeric|min:0',
            'bahan_bakar' => 'sometimes|string|max:50',
            'bahan_bakar_dipakai' => 'sometimes|numeric|min:0',
            'keterangan' => 'sometimes|nullable|string|max:1000',
        ]);
        if ($request->has('km_awal') || $request->has('km_akhir')) {
            $kmAwal = $data['km_awal'] ?? $report->km_awal;
            $kmAkhir = $data['km_akhir'] ?? $report->km_akhir;
            if ($kmAkhir < $kmAwal) {
                return $this->responseError('KM akhir harus lebih besar atau sama dengan KM awal', 422);
            }
            $data['jarak_tempuh'] = $kmAkhir - $kmAwal;
        }
        $report->update($data);
        return $this->responseSuccess($report, AppMessages::SUCCESS_UPDATED);
    }

    // delete laporan harian
    public function destroy(Request $request, $id) {
        $this->authorizeAdmin($request);
        $report = DailyReport::findOrFail($id);
        $report->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }

    //generate laporan untuk semua bus pada tanggal tertentu
    public function generateAll(Request $request) {
        $this->authorizeAdmin($request);
        $tanggal = $request->input('tanggal', now()->toDateString());
        $buses = Bus::all();
        $generated = [];
        $failed = [];
        foreach ($buses as $bus) {
            try {
                $existingReport = DailyReport::where('bus_id', $bus->id)->where('tanggal', $tanggal)->first();
                if ($existingReport) {
                    $failed[] = [
                        'bus_id' => $bus->id,
                        'reason' => 'Laporan untuk tanggal ini sudah ada'
                    ];
                    continue;
                }
                $firstGps = GpsTrack::where('bus_id', $bus->id)->whereDate('recorded_at', $tanggal)->orderBy('recorded_at', 'asc')->first();
                $lastGps = GpsTrack::where('bus_id', $bus->id)->whereDate('recorded_at', $tanggal)->orderBy('recorded_at', 'desc')->first();
                if (!$firstGps || !$lastGps) {
                    $failed[] = [
                        'bus_id' => $bus->id,
                        'reason' => 'Tidak ada data GPS untuk tanggal ini'
                    ];
                    continue;
                }
                $report = DailyReport::create([
                    'bus_id' => $bus->id,
                    'tanggal' => $tanggal,
                    'km_awal' => 0,
                    'km_akhir' => 0,
                    'jarak_tempuh' => 0,
                    'bahan_bakar' => 'Solar',
                    'jam_berangkat' => $firstGps->recorded_at,
                    'jam_tiba' => $lastGps->recorded_at,
                    'total_siswa' => Attendance::where('bus_id', $bus->id)->whereDate('tanggal', $tanggal)->count(),
                    'route_id' => $bus->route_id,
                ]);
                $generated[] = [
                    'bus_id' => $bus->id,
                    'bus_code' => $bus->kode_bus,
                    'report_id' => $report->id,
                    'status' => 'Laporan dibuat, tunggu input KM'
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'bus_id' => $bus->id,
                    'reason' => $e->getMessage()
                ];
            }
        }
        return $this->responseSuccess([
            'tanggal' => $tanggal,
            'total_generated' => count($generated),
            'total_failed' => count($failed),
            'generated' => $generated,
            'failed' => $failed
        ], 'Proses generate laporan selesai');
    }
}
