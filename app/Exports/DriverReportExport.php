<?php

namespace App\Exports;

class DriverReportExport {
    private $reportData;

    public function __construct($reportData) {
        $this->reportData = $reportData;
    }

    public function toArray(): array {
        $rows = [];
        $rows[] = ['LAPORAN PERJALANAN BUS - DETAIL PENUMPANG'];
        $rows[] = ['Tanggal: ' . $this->reportData['tanggal']];
        $rows[] = ['Bus: ' . $this->reportData['bus_code'] . ' (' . $this->reportData['bus_plate'] . ')'];
        $rows[] = ['Driver: ' . $this->reportData['driver_name'] . ' - ' . $this->reportData['driver_phone']];
        $rows[] = ['Total Penumpang: ' . $this->reportData['total_penumpang']];
        $rows[] = [];
        $rows[] = [
            'No.',
            'Nama Penumpang',
            'No. HP',
            'Halte Naik',
            'Waktu Naik',
            'Waktu Turun',
            'Durasi Perjalanan',
            'Lat Naik',
            'Lng Naik',
            'Lat Turun',
            'Lng Turun',
        ];
        foreach ($this->reportData['passengers'] as $index => $passenger) {
            $rows[] = [
                $index + 1,
                $passenger['student_name'],
                $passenger['student_phone'],
                $passenger['halte_naik'],
                $passenger['waktu_naik'],
                $passenger['waktu_turun'],
                $passenger['durasi_perjalanan'],
                $passenger['lat_naik'] ?? '',
                $passenger['lng_naik'] ?? '',
                $passenger['lat_turun'] ?? '',
                $passenger['lng_turun'] ?? '',
            ];
        }
        return $rows;
    }
}
