<?php

namespace App\Http\Controllers\API;

use App\Services\ReportGeneratorService;
use App\Jobs\LogActivityAsync;
use App\Constants\AppMessages;
use App\Models\Bus;
use App\Models\BusDriver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Dompdf\Dompdf;
use App\Exports\AdminReportExport;
use App\Exports\DriverReportExport;
use App\Models\DailyReport;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseController {
    private $reportGenerator;
    public function __construct(ReportGeneratorService $reportGenerator) {
        $this->reportGenerator = $reportGenerator;
        $this->middleware('auth:api');
    }

    //get Laporan admin - summary semua bus
    public function getAdminReport(Request $request) {
        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
        ], [
            'tanggal.required' => 'Tanggal laporan harus diisi (format: YYYY-MM-DD)',
            'tanggal.date_format' => 'Format tanggal tidak valid (gunakan: YYYY-MM-DD)',
        ]);
        $tanggal  = $request->input('tanggal');
        $cacheKey = 'admin_report_' . $tanggal;

        // Jika laporan hari ini, jangan cache (data masih bisa berubah)
        // Jika laporan hari lalu, cache 1 jam
        $isToday = ($tanggal === now()->toDateString());
        if ($isToday) {
            Cache::forget($cacheKey);
        }
        $cacheTtl  = $isToday ? 60 : 3600; // 1 menit untuk hari ini, 1 jam untuk hari lalu
        $reportData = Cache::remember($cacheKey, $cacheTtl, function () use ($tanggal) {
            return $this->reportGenerator->generateAdminReport($tanggal);
        });
        LogActivityAsync::dispatch('report_generated', $request->user()->id, [
            'model'       => 'Report',
            'description' => 'Admin menghasilkan laporan untuk tanggal: ' . $tanggal,
            'status'      => 'success',
        ]);
        return $this->responseSuccess($reportData, 'Laporan berhasil dihasilkan');
    }

    // get laporan driver - detail per bus
    public function getDriverReport(Request $request) {
        $user = $request->user();
        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
            'bus_id' => ['nullable', Rule::exists('buses', 'id')],
        ], [
            'tanggal.required' => 'Tanggal laporan harus diisi',
            'tanggal.date_format' => 'Format tanggal tidak valid (Y-m-d)',
            'bus_id.exists' => 'Bus tidak ditemukan',
        ]);
        $tanggal = $request->input('tanggal');
        $busId = $request->input('bus_id');

        //driver hanya bisa lihat laporan busnya sendiri
        if ($user->role === 'driver') {
            if (!$user->driver) {
                return $this->responseNotFound(AppMessages::ERROR_DRIVER_PROFILE_NOT_FOUND);
            }
            $today = now()->toDateString();
            $activeAssignments = BusDriver::where('driver_id', $user->driver->id)->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $today);
                })->pluck('bus_id')->toArray();
            if (empty($activeAssignments)) {
                return $this->responseNotFound('Anda tidak memiliki penugasan bus aktif');
            }

            // Jika user specify bus_id, validasi bahwa bus itu assign ke driver
            if ($busId) {
                if (!in_array($busId, $activeAssignments)) {
                    return $this->responseForbidden('Bus ini tidak ditugaskan kepada Anda');
                }
            } else {

                // Jika tidak specify, gunakan bus pertama yang aktif, atau jika hanya 1 bus, gunakan itu
                $busId = $activeAssignments[0] ?? null;
            }
        } elseif ($user->role === 'admin') {

            // admin bisa specify bus_id atau harus specify
            if (!$busId) {
                return $this->responseError('Bus harus dipilih', null, 400);
            }
            if (!Bus::where('id', $busId)->exists()) {
                return $this->responseNotFound('Bus tidak ditemukan');
            }
        } else {
            return $this->responseForbidden('Anda tidak memiliki akses ke endpoint ini');
        }
        $cacheKey = 'driver_report_' . $busId . '_' . $tanggal . '_' . $user->id;
        // Jika laporan hari ini, jangan cache (data masih bisa berubah setiap siswa naik/turun)
        // Jika laporan hari lalu, cache 1 jam
        $isToday = ($tanggal === now()->toDateString());
        if ($isToday) {
            Cache::forget($cacheKey);
        }
        $cacheTtl = $isToday ? 60 : 3600; // 1 menit untuk hari ini, 1 jam untuk hari lalu
        $reportData = Cache::remember($cacheKey, $cacheTtl, function () use ($busId, $tanggal, $user) {
            return $this->reportGenerator->generateDriverReport($busId, $tanggal, $user->role === 'driver' ? $user->driver->id : null);
        });
        return $this->responseSuccess($reportData, 'Laporan berhasil dihasilkan');
    }

    //Download laporan admin sebagai PDF
    //GET: ?tanggal=YYYY-MM-DD
    // POST: { "tanggal": "YYYY-MM-DD" }
    public function downloadAdminReportPDF(Request $request) {
        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
        ]);
        $tanggal = $request->input('tanggal');
        $today   = $tanggal;

        // Ambil semua bus yang punya attendance atau daily report hari ini
        $busIdsFromAttendance = \App\Models\Attendance::whereDate('tanggal', $today)
            ->whereNotNull('waktu_naik')
            ->pluck('bus_id')
            ->unique()
            ->toArray();

        $busIdsFromDailyReport = \App\Models\DailyReport::whereDate('tanggal', $today)
            ->pluck('bus_id')
            ->unique()
            ->toArray();

        $busIds = array_unique(array_merge($busIdsFromAttendance, $busIdsFromDailyReport));

        $buses = \App\Models\Bus::whereIn('id', $busIds)->get();

        $busSummary = $buses->map(function ($bus) use ($today) {
            // Cari driver aktif hari ini dari tabel bus_driver langsung
            $activeBusDriver = \App\Models\BusDriver::with('driver.user')
                ->where('bus_id', $bus->id)
                ->where('tanggal_mulai', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_selesai')
                      ->orWhere('tanggal_selesai', '>=', $today);
                })
                ->orderByDesc('tanggal_mulai')
                ->first();

            $driver = $activeBusDriver?->driver;

            // Attendance hari ini untuk bus ini
            $attendances = \App\Models\Attendance::with(['student.user', 'halteNaik'])
                ->where('bus_id', $bus->id)
                ->whereDate('tanggal', $today)
                ->whereNotNull('waktu_naik')
                ->get();

            $boardingCount  = $attendances->count();
            $alightingCount = $attendances->whereNotNull('waktu_turun')->count();
            $belumTurun     = $boardingCount - $alightingCount;

            // Waktu operasi (naik pertama → turun terakhir)
            $waktuMulai = $attendances->whereNotNull('waktu_naik')
                ->sortBy('waktu_naik')->first()?->waktu_naik;
            $waktuSelesai = $attendances->whereNotNull('waktu_turun')
                ->sortByDesc('waktu_turun')->first()?->waktu_turun;

            $durasiOps = '-';
            if ($waktuMulai && $waktuSelesai) {
                $menit = \Carbon\Carbon::parse($waktuMulai)
                    ->diffInMinutes(\Carbon\Carbon::parse($waktuSelesai));
                $jam   = intdiv($menit, 60);
                $sisa  = $menit % 60;
                $durasiOps = $jam > 0 ? "{$jam}j {$sisa}m" : "{$sisa}m";
            }

            // Kecepatan rata-rata GPS hari ini
            $gpsData = \App\Models\GpsTrack::where('bus_id', $bus->id)
                ->whereDate('recorded_at', $today)
                ->where('speed', '>', 0)
                ->avg('speed');
            $avgSpeed = $gpsData ? round($gpsData, 1) . ' km/h' : '-';

            // Halte yang paling banyak dipakai
            $topHalte = $attendances->groupBy('halte_naik_id')
                ->map(fn($g) => [
                    'nama'  => $g->first()?->halteNaik?->nama_halte ?? '-',
                    'count' => $g->count(),
                ])
                ->sortByDesc('count')
                ->first();

            // Cari laporan harian jika ada
            $dailyReport = \App\Models\DailyReport::where('bus_id', $bus->id)
                ->whereDate('tanggal', $today)
                ->first();

            return [
                'bus_code'        => $bus->kode_bus ?? '-',
                'bus_plate'       => $bus->plat_nomor ?? '-',
                'driver_name'     => $driver?->user?->name ?? '-',
                'driver_phone'    => $driver?->no_hp ?? '-',
                'total_penumpang' => $boardingCount,
                'boarding_count'  => $boardingCount,
                'alighting_count' => $alightingCount,
                'belum_turun'     => $belumTurun,
                'durasi_operasi'  => $durasiOps,
                'avg_speed'       => $avgSpeed,
                'top_halte'       => $topHalte
                    ? $topHalte['nama'] . ' (' . $topHalte['count'] . 'x)'
                    : '-',
                'catatan'         => $dailyReport?->catatan_driver ?? '-',
                'waktu_mulai'     => $waktuMulai
                    ? \Carbon\Carbon::parse($waktuMulai)
                        ->setTimezone(config('app.timezone'))
                        ->format('H:i')
                    : '-',
                'waktu_selesai'   => $waktuSelesai
                    ? \Carbon\Carbon::parse($waktuSelesai)
                        ->setTimezone(config('app.timezone'))
                        ->format('H:i')
                    : '-',
            ];
        });

        $totalPenumpang  = $busSummary->sum('boarding_count');
        $totalCheckout   = $busSummary->sum('alighting_count');
        $totalBelumTurun = $busSummary->sum('belum_turun');
        $completion      = $totalPenumpang > 0
            ? round(($totalCheckout / $totalPenumpang) * 100, 1) : 0;

        // Halte tersibuk hari ini (lintas semua bus)
        $allAttendances = \App\Models\Attendance::with('halteNaik')
            ->whereDate('tanggal', $today)
            ->whereNotNull('waktu_naik')
            ->get();
        $halteStats = $allAttendances->groupBy('halte_naik_id')
            ->map(fn($g) => [
                'nama'  => $g->first()?->halteNaik?->nama_halte ?? '-',
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->toArray();

        $viewData = [
            'report' => [
                'tanggal'          => $tanggal,
                'total_buses'      => $buses->count(),
                'total_passengers' => $totalPenumpang,
                'total_checkout'   => $totalCheckout,
                'total_belum_turun'=> $totalBelumTurun,
                'completion_rate'  => $completion,
                'buses'            => $busSummary->values()->toArray(),
                'halte_stats'      => $halteStats,
            ],
        ];

        $html = view('reports.admin-report', $viewData)->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $pdfContent = $dompdf->output();
        $filename = "laporan_harian_admin_{$tanggal}.pdf";
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    //download laporan driver sebagai PDF
    // GET: ?bus_id=X&tanggal=YYYY-MM-DD
    // POST: { "bus_id":X, "tanggal":"YYYY-MM-DD" }
    public function downloadDriverReportPDF(Request $request) {
        $request->validate([
            'bus_id' => ['nullable', Rule::exists('buses', 'id')],
            'tanggal' => 'required|date_format:Y-m-d',
            'catatan_driver' => 'nullable|string',
        ]);
        $busId         = $request->input('bus_id');
        $tanggal       = $request->input('tanggal');
        $catatanDriver = $request->input('catatan_driver');
        $this->saveCatatanDriver($busId, $tanggal, $catatanDriver);

        $result = $this->reportGenerator->generateDriverReportPDF($busId, $tanggal);

        // Gunakan nama driver untuk nama file agar jelas
        $pdfContent = is_array($result) ? $result['content'] : $result;
        $namaDriver = is_array($result) ? ($result['driver_name'] ?? 'driver') : 'driver';
        $safeDriver = preg_replace('/[^A-Za-z0-9_\-]/', '_', $namaDriver);
        $filename   = "Laporan_{$safeDriver}_{$tanggal}.pdf";

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // Download laporan admin sebagai Excel (XLSX)
    public function downloadAdminReportExcel(Request $request) {
        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
        ]);
        $tanggal = $request->input('tanggal');
        $reportData = $this->reportGenerator->generateAdminReport($tanggal);
        $filename = 'Laporan_Admin_' . $tanggal . '.xlsx';
        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            return \Maatwebsite\Excel\Facades\Excel::download(new AdminReportExport($reportData), $filename);
        }
        $rows = (new AdminReportExport($reportData))->toArray();
        $csv = $this->arrayToCsv($rows);
        return response($csv, 200)->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // Download laporan driver sebagai Excel (XLSX)
    // GET: ?bus_id=X&tanggal=YYYY-MM-DD
    // POST: { "bus_id":X, "tanggal":"YYYY-MM-DD" }
    public function downloadDriverReportExcel(Request $request) {
        $request->validate([
            'bus_id' => ['nullable', Rule::exists('buses', 'id')],
            'tanggal' => 'required|date_format:Y-m-d',
            'catatan_driver' => 'nullable|string',
        ]);
        $busId         = $request->input('bus_id');
        $tanggal       = $request->input('tanggal');
        $catatanDriver = $request->input('catatan_driver');
        $this->saveCatatanDriver($busId, $tanggal, $catatanDriver);
        if (!$busId && $request->user() && $request->user()->role === 'driver') {
            $driver = $request->user()->driver;
            if ($driver) {
                $assignment = BusDriver::where('driver_id', $driver->id)->active()->first();
                if ($assignment) {
                    $busId = $assignment->bus_id;
                }
            }
        }
        if (!$busId) {
            abort(400, 'Parameter bus_id is required for this operation');
        }
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $result   = $this->reportGenerator->generateDriverAttendanceExcel($busId, $tanggal);
            $filePath = is_array($result) ? $result['path'] : $result;
            $namaDriver = is_array($result) ? ($result['driver_name'] ?? 'driver') : 'driver';
            $safeDriver = preg_replace('/[^A-Za-z0-9_\-]/', '_', $namaDriver);
            $filename   = "Laporan_{$safeDriver}_{$tanggal}.xlsx";
            if (file_exists($filePath)) {
                return response()->download($filePath, $filename)->deleteFileAfterSend(true);
            }
        }
        // Fallback CSV jika PhpSpreadsheet tidak tersedia
        $reportData = $this->reportGenerator->generateDriverAttendanceReport($busId, $tanggal);
        $rows = [];
        $rows[] = ['No', 'Nama Penumpang', 'Waktu Naik', 'Halte Naik', 'Waktu Turun', 'Status Checkout', 'Plat', 'No Telepon Driver'];
        foreach ($reportData['reports'] as $r) {
            $wn = isset($r['waktu_naik'])  && $r['waktu_naik']  ? \Carbon\Carbon::parse($r['waktu_naik'])->format('H:i')  : '-';
            $wt = isset($r['waktu_turun']) && $r['waktu_turun'] ? \Carbon\Carbon::parse($r['waktu_turun'])->format('H:i') : '-';
            $rows[] = [
                $r['no'] ?? '-',
                $r['nama_penumpang'] ?? '-',
                $wn,
                $r['halte_naik'] ?? '-',
                $wt,
                ($r['checkout'] ?? 'No') === 'Yes' ? 'Sudah Turun' : 'Masih Di Bus',
                $r['plat'] ?? '-',
                $r['no_telepon'] ?? '-',
            ];
        }
        $filename = "Laporan_driver_{$tanggal}.xlsx";
        $csv = $this->arrayToCsv($rows);
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Simpan catatan driver ke DailyReport (dipakai PDF dan Excel).
     * Extracted dari downloadDriverReportPDF dan downloadDriverReportExcel untuk menghindari duplikasi.
     */
    private function saveCatatanDriver(?int $busId, string $tanggal, ?string $catatanDriver): void {
        if (!$busId) return;
        DailyReport::updateOrCreate(
            ['bus_id' => $busId, 'tanggal' => $tanggal],
            ['catatan_driver' => $catatanDriver ?? DB::raw('catatan_driver')]
        );
    }

    private function arrayToCsv(array $rows): string {
        $output = '';
        foreach ($rows as $row) {
            $escaped = array_map(function ($cell) {
                $str = (string) $cell;
                $str = str_replace('"', '""', $str);
                return '"' . $str . '"';
            }, $row);
            $output .= implode(',', $escaped) . "\n";
        }
        return $output;
    }
}