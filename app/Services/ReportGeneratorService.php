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
        $dailyReports = DailyReport::whereDate('created_at', $date)->get();
        $reportData = $dailyReports->map(function ($report) {
            return [
                'bus_driver' => $report->bus_driver,
                'jumlah_penumpang' => $report->jumlah_penumpang,
            ];
        });
        return [
            'total_reports' => $dailyReports->count(),
            'reports' => $reportData,
        ];
    }

    public function generateDriverReport($busId, $tanggal, $driverId = null) {
        $date = Carbon::parse($tanggal)->toDateString();
        $dailyReports = DailyReport::where('bus_id', $busId)->whereDate('created_at', $date)->when($driverId, function ($query) use ($driverId) {
                $query->where('driver_id', $driverId);
            })->get();
        \Log::info('Daily Reports Query Result', ['query_result' => $dailyReports->toArray()]);
        $reportData = $dailyReports->map(function ($report) {
            return [
                'bus_driver' => $report->bus_driver,
                'jumlah_penumpang' => $report->jumlah_penumpang,
                'route' => $report->route,
                'duration' => $report->duration,
            ];
        });
        return [
            'total_reports' => $dailyReports->count(),
            'reports' => $reportData,
        ];
    }

    public function generateDriverReportPDF($busId, $tanggal) {
        $bus = Bus::with(['driver.user'])->find($busId);
        $reportData = $this->generateDriverAttendanceReport($busId, $tanggal);
        $dompdf = new Dompdf();
        $css = file_get_contents(resource_path('css/pdf_styles.css'));
        $html = "<html><head><style>{$css}</style></head><body>";
        $html .= "<h1>Laporan Harian Driver</h1>";
        $html .= "<ul>";
        $html .= "<li><strong>Kode Bus:</strong> {$bus->kode_bus}</li>";
        $html .= "<li><strong>Plat:</strong> {$reportData['reports'][0]['plat']}</li>";
        $html .= "<li><strong>Nama Driver:</strong> {$bus->driver->user->name}</li>";
        $html .= "<li><strong>No Telepon:</strong> {$reportData['reports'][0]['no_telepon']}</li>";
        $html .= "<li><strong>Tanggal:</strong> {$tanggal}</li>";
        $html .= "</ul>";
        $html .= "<table>";
        $html .= "<thead><tr><th>No</th><th>Nama Penumpang</th><th>Waktu Naik</th><th>Halte Naik</th><th>Waktu Turun</th><th>Lat, Lng Turun</th><th>Checkout</th></tr></thead><tbody>";
        foreach ($reportData['reports'] as $report) {
            $html .= "<tr>";
            $html .= "<td>{$report['no']}</td>";
            $html .= "<td>{$report['nama_penumpang']}</td>";
            $html .= "<td>{$report['waktu_naik']}</td>";
            $html .= "<td>{$report['halte_naik']}</td>";
            $html .= "<td>{$report['waktu_turun']}</td>";
            $html .= "<td>{$report['lat_lng_turun']}</td>";
            $html .= "<td>{$report['checkout']}</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
        $html .= "</body></html>";
        $dompdf->loadHtml($html);
        $dompdf->render();
        return $dompdf->output();
    }

    private function calculateDuration($waktuNaik, $waktuTurun) {
        if (!$waktuNaik || !$waktuTurun) {
            return '-';
        }
        $start = Carbon::parse($waktuNaik);
        $end = Carbon::parse($waktuTurun);
        $duration = $end->diffInMinutes($start);
        $hours = intdiv($duration, 60);
        $minutes = $duration % 60;
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }

    public function generateDriverAttendanceReport($busId, $tanggal) {
        $date = Carbon::parse($tanggal)->toDateString();
        $attendances = Attendance::with(['student.user', 'bus.drivers', 'halteNaik'])->where('bus_id', $busId)->whereDate('tanggal', $date)->get();
        $reportData = $attendances->map(function ($attendance, $index) use ($date) {
            $driver = $attendance->bus->drivers->first();
            return [
                'no' => $index + 1,
                'nama_penumpang' => $attendance->student->user->name ?? '-',
                'waktu_naik' => $attendance->waktu_naik,
                'halte_naik' => $attendance->halteNaik->nama_halte ?? '-',
                'waktu_turun' => $attendance->waktu_turun,
                'lat_lng_turun' => $attendance->lat_turun . ', ' . $attendance->lng_turun,
                'checkout' => $attendance->waktu_turun ? 'Yes' : 'No',
                'plat' => $attendance->bus->plat_nomor ?? '-',
                'no_telepon' => $driver->no_hp ?? '-',
                'tanggal' => $date,
            ];
        });
        return [
            'total_attendances' => $attendances->count(),
            'reports' => $reportData,
        ];
    }

    public function generateDriverAttendanceExcel($busId, $tanggal) {
        $reportData = $this->generateDriverAttendanceReport($busId, $tanggal);
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'No')->setCellValue('B1', 'Nama Penumpang')->setCellValue('C1', 'Waktu Naik')->setCellValue('D1', 'Halte Naik')->setCellValue('E1', 'Waktu Turun')->setCellValue('F1', 'Lat, Lng Turun')->setCellValue('G1', 'Checkout')->setCellValue('H1', 'Plat')->setCellValue('I1', 'No Telepon');
        $row = 2;
        foreach ($reportData['reports'] as $report) {
            $sheet->setCellValue("A{$row}", $report['no'])->setCellValue("B{$row}", $report['nama_penumpang'])->setCellValue("C{$row}", $report['waktu_naik'])->setCellValue("D{$row}", $report['halte_naik'])->setCellValue("E{$row}", $report['waktu_turun'])->setCellValue("F{$row}", $report['lat_lng_turun'])->setCellValue("G{$row}", $report['checkout'])->setCellValue("H{$row}", $report['plat'])->setCellValue("I{$row}", $report['no_telepon']);
            $row++;
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $dir = storage_path('app/reports');
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $filePath = $dir . DIRECTORY_SEPARATOR . 'driver_attendance_report.xlsx';
        $writer->save($filePath);
        return $filePath;
    }
}
