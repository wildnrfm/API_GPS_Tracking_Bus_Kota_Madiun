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
        $tanggal = $request->input('tanggal');
        $cacheKey = 'admin_report_' . $tanggal;
        $reportData = Cache::remember($cacheKey, 3600, function () use ($tanggal) {
            return $this->reportGenerator->generateAdminReport($tanggal);
        });
        LogActivityAsync::dispatch('report_generated', $request->user()->id, [
            'model' => 'Report',
            'description' => 'Admin menghasilkan laporan untuk tanggal: ' . $tanggal,
            'status' => 'success',
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
        $reportData = Cache::remember($cacheKey, 3600, function () use ($busId, $tanggal, $user) {
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
        $dailyReports = DailyReport::with(['bus', 'bus.driver', 'bus.driver.user'])->whereDate('created_at', $tanggal)->get();
        $html = '<h1 style="text-align: center;">Laporan Harian Admin</h1>';
        $html .= '<p style="text-align: center; font-size: 14px;">' . e($tanggal) . '</p>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
        $html .= '<thead><tr><th>No.</th><th>Kode Bus</th><th>Plat</th><th>Driver</th><th>No Telepon</th><th>Total Penumpang</th><th>Catatan Driver</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($dailyReports as $index => $report) {
            $html .= '<tr>' .
                        '<td>' . ($index + 1) . '</td>' .
                        '<td>' . e($report->bus->kode_bus ?? '-') . '</td>' .
                        '<td>' . e($report->bus->plat_nomor ?? '-') . '</td>' .
                        '<td>' . e($report->bus->driver->user->name ?? '-') . '</td>' .
                        '<td>' . e($report->bus->driver->no_hp ?? '-') . '</td>' .
                        '<td>' . e($report->total_penumpang) . '</td>' .
                        '<td>' . e($report->catatan_driver) . '</td>' .
                    '</tr>';
        }
        $html .= '</tbody></table>';
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();
        $filename = "laporan_harian_admin_{$tanggal}.pdf";
        return response($pdfContent, 200)->header('Content-Type', 'application/pdf')->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
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
        $pdfContent = $this->reportGenerator->generateDriverReportPDF($busId, $tanggal);
        $filename = "driver_report_{$busId}_{$tanggal}.pdf";
        return response($pdfContent, 200)->header('Content-Type', 'application/pdf')->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
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
        $filename = "driver_report_{$busId}_{$tanggal}.xlsx";
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $filePath = $this->reportGenerator->generateDriverAttendanceExcel($busId, $tanggal);
            if (file_exists($filePath)) {
                return response()->download($filePath, $filename)->deleteFileAfterSend(true);
            }
        }
        $reportData = $this->reportGenerator->generateDriverAttendanceReport($busId, $tanggal);
        $rows = [];
        $rows[] = ['No', 'Nama Penumpang', 'Waktu Naik', 'Halte Naik', 'Waktu Turun', 'Lat, Lng Turun', 'Checkout', 'Plat', 'No Telepon'];
        foreach ($reportData['reports'] as $r) {
            $rows[] = [
                $r['no'] ?? '-',
                $r['nama_penumpang'] ?? '-',
                $r['waktu_naik'] ?? '-',
                $r['halte_naik'] ?? '-',
                $r['waktu_turun'] ?? '-',
                $r['lat_lng_turun'] ?? '-',
                $r['checkout'] ?? '-',
                $r['plat'] ?? '-',
                $r['no_telepon'] ?? '-',
            ];
        }
        $csv = $this->arrayToCsv($rows);
        return response($csv, 200)->header('Content-Type', 'text/csv')->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
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
