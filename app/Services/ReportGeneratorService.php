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
            'reports'            => $reportData->values()->toArray(),
            'attendance_reports' => $attendanceReport['reports']->values()->toArray(),
        ];
    }

    public function generateDriverReportPDF($busId, $tanggal) {
        $date       = Carbon::parse($tanggal)->toDateString();
        $bus        = Bus::find($busId);
        $reportData = $this->generateDriverAttendanceReport($busId, $tanggal);

        $activeDriver = BusDriver::with('driver.user')
            ->where('bus_id', $busId)
            ->where('tanggal_mulai', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $date);
            })
            ->orderByDesc('tanggal_mulai')
            ->first();

        $namaDriver    = $activeDriver?->driver->user->name ?? '-';
        $noTelepon     = $activeDriver?->driver->no_hp ?? '-';
        $platNomor     = $bus->plat_nomor ?? '-';
        $kodeBus       = $bus->kode_bus ?? '-';
        $totalSiswa    = $reportData['total_attendances'];
        $totalCheckout = collect($reportData['reports'])->where('checkout', 'Yes')->count();

        // Siapkan data passengers dengan durasi perjalanan
        $passengers = collect($reportData['reports'])->map(function ($r) {
            $durasi = '-';
            if ($r['waktu_naik'] && $r['waktu_turun']) {
                $durasi = $this->calculateDuration(
                    Carbon::parse($r['waktu_naik']),
                    Carbon::parse($r['waktu_turun'])
                );
            }
            return array_merge($r, ['durasi_perjalanan' => $durasi]);
        })->toArray();

        // Render HTML dari blade view
        $viewData = [
            'report' => [
                'driver_name'      => $namaDriver,
                'driver_phone'     => $noTelepon,
                'bus_code'         => $kodeBus,
                'bus_plate'        => $platNomor,
                'tanggal'          => $tanggal,
                'total_penumpang'  => $totalSiswa,
                'penumpang_naik'   => $totalSiswa,
                'penumpang_turun'  => $totalCheckout,
                'passengers'       => $passengers,
            ],
        ];

        $html = view('reports.driver-report', $viewData)->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return ['content' => $dompdf->output(), 'driver_name' => $namaDriver];
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
        // Hanya ambil record yang sudah benar-benar naik (checked_in / checked_out)
        // Exclude 'pending' — record pending adalah QR yang belum discan driver
        // sehingga waktu_naik masih null dan halte_naik belum tentu akurat
        $attendances = Attendance::with(['student.user', 'bus', 'halteNaik'])
            ->where('bus_id', $busId)
            ->whereDate('tanggal', $date)
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereNotNull('waktu_naik')
            ->orderBy('waktu_naik', 'asc')
            ->get();

        // Ambil driver AKTIF pada tanggal laporan (bukan sekedar driver pertama di bus)
        $activeDriver = BusDriver::with('driver.user')
            ->where('bus_id', $busId)
            ->where('tanggal_mulai', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $date);
            })
            ->orderByDesc('tanggal_mulai')
            ->first();

        $reportData = $attendances->map(function ($attendance, $index) use ($date, $activeDriver) {
            $driver = $activeDriver?->driver;
            // Format waktu sebagai string ISO agar konsisten di frontend
            // $attendance->waktu_naik sudah di-cast sebagai datetime di model
            $waktuNaikStr  = $attendance->waktu_naik  ? $attendance->waktu_naik->setTimezone(config('app.timezone'))->toIso8601String()  : null;
            $waktuTurunStr = $attendance->waktu_turun ? $attendance->waktu_turun->setTimezone(config('app.timezone'))->toIso8601String() : null;
            return [
                'no'             => $index + 1,
                'nama_penumpang' => $attendance->student->user->name ?? '-',
                'waktu_naik'     => $waktuNaikStr,
                'halte_naik'     => $attendance->halteNaik->nama_halte ?? '-',
                'waktu_turun'    => $waktuTurunStr,
                'lat_lng_turun'  => ($attendance->lat_turun ?? '-') . ', ' . ($attendance->lng_turun ?? '-'),
                'checkout'       => $attendance->waktu_turun ? 'Yes' : 'No',
                'plat'           => $attendance->bus->plat_nomor ?? '-',
                'no_telepon'     => $attendance->student->no_hp ?? '-',
                'tanggal'        => $date,
            ];
        });

        return [
            'total_attendances' => $attendances->count(),
            'reports'           => $reportData->values(),
        ];
    }

    public function generateDriverAttendanceExcel($busId, $tanggal) {
        $reportData  = $this->generateDriverAttendanceReport($busId, $tanggal);
        $date        = Carbon::parse($tanggal)->toDateString();
        $bus         = \App\Models\Bus::find($busId);

        // Ambil driver aktif pada tanggal laporan
        $activeDriver = BusDriver::with('driver.user')
            ->where('bus_id', $busId)
            ->where('tanggal_mulai', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $date);
            })
            ->orderByDesc('tanggal_mulai')
            ->first();

        $namaDriver  = $activeDriver?->driver->user->name ?? '-';
        $noTelepon   = $activeDriver?->driver->no_hp ?? '-';
        $kodeBus     = $bus->kode_bus ?? '-';
        $platNomor   = $bus->plat_nomor ?? '-';
        $totalSiswa  = $reportData['total_attendances'];
        $totalCheckout = collect($reportData['reports'])->where('checkout', 'Yes')->count();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Operasional');

        // ── Styling helpers ────────────────────────────────
        $boldStyle   = ['font' => ['bold' => true]];
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => '2E7D32']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $centerStyle = ['alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]];

        // ── Baris 1: Judul ────────────────────────────────
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'LAPORAN OPERASIONAL HARIAN BUS');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1A1A2E']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Baris 3-8: Info laporan ───────────────────────
        $infoRows = [
            ['Kode Bus',         $kodeBus],
            ['Plat Nomor',       $platNomor],
            ['Nama Driver',      $namaDriver],
            ['No. Telepon',      $noTelepon],
            ['Tanggal',          Carbon::parse($tanggal)->translatedFormat('d F Y')],
            ['Total Penumpang',  $totalSiswa . ' siswa'],
            ['Sudah Checkout',   $totalCheckout . ' dari ' . $totalSiswa . ' siswa'],
        ];
        $infoRow = 3;
        foreach ($infoRows as [$label, $value]) {
            $sheet->setCellValue("A{$infoRow}", $label);
            $sheet->setCellValue("B{$infoRow}", $value);
            $sheet->getStyle("A{$infoRow}")->applyFromArray($boldStyle);
            $infoRow++;
        }

        // ── Baris header tabel ────────────────────────────
        $tableStart = $infoRow + 1;
        $headers = ['No', 'Nama Penumpang', 'Waktu Naik', 'Halte Naik',
                    'Waktu Turun', 'Status Checkout', 'Plat Nomor', 'No. Telepon Driver'];
        $cols = ['A','B','C','D','E','F','G','H'];
        foreach ($cols as $i => $col) {
            $sheet->setCellValue("{$col}{$tableStart}", $headers[$i]);
        }
        $sheet->getStyle("A{$tableStart}:H{$tableStart}")->applyFromArray($headerStyle);

        // ── Data rows ─────────────────────────────────────
        $dataRow = $tableStart + 1;
        foreach ($reportData['reports'] as $report) {
            $waktuNaik  = $report['waktu_naik']  ? Carbon::parse($report['waktu_naik'])->format('H:i')  : '-';
            $waktuTurun = $report['waktu_turun'] ? Carbon::parse($report['waktu_turun'])->format('H:i') : '-';
            $status     = $report['checkout'] === 'Yes' ? '✓ Sudah Turun' : '⏳ Masih Di Bus';

            $sheet->setCellValue("A{$dataRow}", $report['no'])
                  ->setCellValue("B{$dataRow}", $report['nama_penumpang'])
                  ->setCellValue("C{$dataRow}", $waktuNaik)
                  ->setCellValue("D{$dataRow}", $report['halte_naik'])
                  ->setCellValue("E{$dataRow}", $waktuTurun)
                  ->setCellValue("F{$dataRow}", $status)
                  ->setCellValue("G{$dataRow}", $report['plat'])
                  ->setCellValue("H{$dataRow}", $report['no_telepon']);

            // Warna baris bergantian
            $bgColor = ($dataRow % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
            $sheet->getStyle("A{$dataRow}:H{$dataRow}")->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => $bgColor]],
            ]);
            $dataRow++;
        }

        // ── Auto width kolom ──────────────────────────────
        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Baris ringkasan bawah ─────────────────────────
        $dataRow++;
        $sheet->mergeCells("A{$dataRow}:H{$dataRow}");
        $sheet->setCellValue("A{$dataRow}", "Dicetak: " . now()->format('d/m/Y H:i'));
        $sheet->getStyle("A{$dataRow}")->applyFromArray([
            'font'      => ['italic' => true, 'color' => ['rgb' => '888888']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
        ]);

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $dir      = storage_path('app/reports');
        if (!file_exists($dir)) mkdir($dir, 0755, true);

        // Nama file pakai nama driver & tanggal
        $safeDriverName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $namaDriver);
        $filePath = $dir . DIRECTORY_SEPARATOR . "laporan_{$safeDriverName}_{$tanggal}.xlsx";
        $writer->save($filePath);

        return ['path' => $filePath, 'driver_name' => $namaDriver];
    }
}