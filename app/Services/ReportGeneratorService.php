<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Bus;
use App\Models\BusDriver;
use App\Models\DailyReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Dompdf\Dompdf;

class ReportGeneratorService {

    public function generateAdminReport($tanggal) {
        $date = Carbon::parse($tanggal)->toDateString();

        // FIX Bug 1: Gunakan 'tanggal' bukan 'created_at', dan pakai kolom yang benar
        $dailyReports = DailyReport::with(['bus', 'busDriver'])
            ->whereDate('tanggal', $date)
            ->get();

        $reportData = $dailyReports->map(function ($report) {
            return [
                // FIX: 'bus_driver' tidak ada → pakai relasi busDriver
                'bus_driver_id'   => $report->bus_driver_id,
                'bus_driver'      => $report->busDriver ?? null,
                // FIX: 'jumlah_penumpang' tidak ada → pakai 'total_penumpang'
                'total_penumpang' => $report->total_penumpang,
                'catatan_driver'  => $report->catatan_driver,
                'bus'             => $report->bus,
            ];
        });

        return [
            'total_reports' => $dailyReports->count(),
            'reports'       => $reportData,
        ];
    }

    public function generateDriverReport($busId, $tanggal, $driverId = null) {
        $date = Carbon::parse($tanggal)->toDateString();

        // FIX Bug 1: Gunakan 'tanggal' bukan 'created_at' agar query sesuai kolom model
        // FIX: 'driver_id' tidak ada di DailyReport → filter via busDriver jika perlu
        $dailyReports = DailyReport::with(['bus', 'busDriver'])
            ->where('bus_id', $busId)
            ->whereDate('tanggal', $date)
            ->get();

        \Log::info('Daily Reports Query Result', ['query_result' => $dailyReports->toArray()]);

        // Ambil data attendance untuk laporan yang lebih lengkap
        $attendanceReport = $this->generateDriverAttendanceReport($busId, $tanggal);

        // Hitung durasi operasi dari data attendance
        $duration = $this->calculateOperationDuration($attendanceReport['reports']);

        $reportData = $dailyReports->map(function ($report) {
            return [
                // FIX: pakai kolom & relasi yang benar sesuai model DailyReport
                'bus_driver_id'   => $report->bus_driver_id,
                'bus_driver'      => $report->busDriver ?? null,
                // FIX: 'jumlah_penumpang' → 'total_penumpang'
                'total_penumpang' => $report->total_penumpang,
                'catatan_driver'  => $report->catatan_driver,
                // FIX: 'route' & 'duration' tidak ada di model → hapus / isi dari attendance
            ];
        });

        return [
            'total_reports'      => $dailyReports->count(),
            'total_attendances'  => $attendanceReport['total_attendances'],
            'duration'           => $duration,
            'reports'            => $reportData,
            'attendance_reports' => $attendanceReport['reports'],
        ];
    }

    public function generateDriverReportPDF($busId, $tanggal) {
        $bus        = Bus::with(['driver.user'])->find($busId);
        $reportData = $this->generateDriverAttendanceReport($busId, $tanggal);

        // FIX Bug 2: Null-safe jika tidak ada data attendance (reports kosong)
        $firstReport = $reportData['reports'][0] ?? null;
        $plat        = $firstReport['plat']       ?? ($bus->plat_nomor ?? '-');
        $noTelepon   = $firstReport['no_telepon'] ?? '-';

        // FIX Bug 3: Null-safe akses relasi bus->driver->user->name
        $namaDriver  = $bus->driver->user->name ?? '-';
        $kodeBus     = $bus->kode_bus ?? '-';

        $dompdf = new Dompdf();

        // FIX: Tangani jika file CSS tidak ditemukan agar tidak crash
        $cssPath = resource_path('css/pdf_styles.css');
        $css     = file_exists($cssPath) ? file_get_contents($cssPath) : '';

        $html  = "<html><head><style>{$css}</style></head><body>";
        $html .= "<h1>Laporan Harian Driver</h1>";
        $html .= "<ul>";
        $html .= "<li><strong>Kode Bus:</strong> " . e($kodeBus) . "</li>";
        $html .= "<li><strong>Plat:</strong> "      . e($plat) . "</li>";
        $html .= "<li><strong>Nama Driver:</strong> " . e($namaDriver) . "</li>";
        $html .= "<li><strong>No Telepon:</strong> "  . e($noTelepon) . "</li>";
        $html .= "<li><strong>Tanggal:</strong> "     . e($tanggal) . "</li>";
        $html .= "</ul>";
        $html .= "<table>";
        $html .= "<thead><tr>
                    <th>No</th>
                    <th>Nama Penumpang</th>
                    <th>Waktu Naik</th>
                    <th>Halte Naik</th>
                    <th>Waktu Turun</th>
                    <th>Lat, Lng Turun</th>
                    <th>Checkout</th>
                  </tr></thead><tbody>";

        // FIX Bug 2: Tangani jika reports kosong — tampilkan baris kosong
        if (empty($reportData['reports']) || $reportData['reports']->isEmpty()) {
            $html .= "<tr><td colspan='7' style='text-align:center;'>Tidak ada data absensi untuk tanggal ini.</td></tr>";
        } else {
            foreach ($reportData['reports'] as $report) {
                $html .= "<tr>";
                $html .= "<td>" . e($report['no'])             . "</td>";
                $html .= "<td>" . e($report['nama_penumpang']) . "</td>";
                $html .= "<td>" . e($report['waktu_naik'])     . "</td>";
                $html .= "<td>" . e($report['halte_naik'])     . "</td>";
                $html .= "<td>" . e($report['waktu_turun'])    . "</td>";
                $html .= "<td>" . e($report['lat_lng_turun'])  . "</td>";
                $html .= "<td>" . e($report['checkout'])       . "</td>";
                $html .= "</tr>";
            }
        }

        $html .= "</tbody></table>";
        $html .= "</body></html>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Hitung durasi operasi dari data attendance (waktu naik pertama → waktu turun terakhir)
     */
    private function calculateOperationDuration($reports): string {
        if (empty($reports) || (is_object($reports) && $reports->isEmpty())) {
            return '-';
        }

        $reportsArray = is_object($reports) ? $reports->toArray() : $reports;

        $waktuNaikList = array_filter(array_column($reportsArray, 'waktu_naik'));
        $waktuTurunList = array_filter(array_column($reportsArray, 'waktu_turun'));

        if (empty($waktuNaikList) || empty($waktuTurunList)) {
            return '-';
        }

        $waktuNaikParsed  = array_map(fn($t) => Carbon::parse($t), $waktuNaikList);
        $waktuTurunParsed = array_map(fn($t) => Carbon::parse($t), $waktuTurunList);

        usort($waktuNaikParsed,  fn($a, $b) => $a->timestamp - $b->timestamp);
        usort($waktuTurunParsed, fn($a, $b) => $a->timestamp - $b->timestamp);

        $start = $waktuNaikParsed[0];
        $end   = end($waktuTurunParsed);

        return $this->calculateDuration($start, $end);
    }

    private function calculateDuration($waktuNaik, $waktuTurun): string {
        if (!$waktuNaik || !$waktuTurun) {
            return '-';
        }
        $start    = $waktuNaik instanceof Carbon ? $waktuNaik : Carbon::parse($waktuNaik);
        $end      = $waktuTurun instanceof Carbon ? $waktuTurun : Carbon::parse($waktuTurun);
        $duration = $end->diffInMinutes($start);
        $hours    = intdiv($duration, 60);
        $minutes  = $duration % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }

    public function generateDriverAttendanceReport($busId, $tanggal) {
        $date        = Carbon::parse($tanggal)->toDateString();
        $attendances = Attendance::with(['student.user', 'bus.drivers', 'halteNaik'])
            ->where('bus_id', $busId)
            ->whereDate('tanggal', $date)
            ->get();

        $reportData = $attendances->map(function ($attendance, $index) use ($date) {
            $driver = $attendance->bus->drivers->first();
            return [
                'no'             => $index + 1,
                'nama_penumpang' => $attendance->student->user->name ?? '-',
                'waktu_naik'     => $attendance->waktu_naik,
                'halte_naik'     => $attendance->halteNaik->nama_halte ?? '-',
                'waktu_turun'    => $attendance->waktu_turun,
                'lat_lng_turun'  => ($attendance->lat_turun ?? '-') . ', ' . ($attendance->lng_turun ?? '-'),
                'checkout'       => $attendance->waktu_turun ? 'Yes' : 'No',
                'plat'           => $attendance->bus->plat_nomor ?? '-',
                'no_telepon'     => $driver->no_hp ?? '-',
                'tanggal'        => $date,
            ];
        });

        return [
            'total_attendances' => $attendances->count(),
            'reports'           => $reportData,
        ];
    }

    public function generateDriverAttendanceExcel($busId, $tanggal) {
        $reportData  = $this->generateDriverAttendanceReport($busId, $tanggal);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'No')
              ->setCellValue('B1', 'Nama Penumpang')
              ->setCellValue('C1', 'Waktu Naik')
              ->setCellValue('D1', 'Halte Naik')
              ->setCellValue('E1', 'Waktu Turun')
              ->setCellValue('F1', 'Lat, Lng Turun')
              ->setCellValue('G1', 'Checkout')
              ->setCellValue('H1', 'Plat')
              ->setCellValue('I1', 'No Telepon');

        $row = 2;
        foreach ($reportData['reports'] as $report) {
            $sheet->setCellValue("A{$row}", $report['no'])
                  ->setCellValue("B{$row}", $report['nama_penumpang'])
                  ->setCellValue("C{$row}", $report['waktu_naik'])
                  ->setCellValue("D{$row}", $report['halte_naik'])
                  ->setCellValue("E{$row}", $report['waktu_turun'])
                  ->setCellValue("F{$row}", $report['lat_lng_turun'])
                  ->setCellValue("G{$row}", $report['checkout'])
                  ->setCellValue("H{$row}", $report['plat'])
                  ->setCellValue("I{$row}", $report['no_telepon']);
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $dir    = storage_path('app/reports');

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . 'driver_attendance_report.xlsx';
        $writer->save($filePath);

        return $filePath;
    }
}