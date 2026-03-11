<?php

namespace App\Exports;

class AdminReportExport {
    private $reportData;

    public function __construct($reportData) {
        $this->reportData = $reportData;
    }

    public function toArray(): array {
        $rows = [];
        $rows[] = ['LAPORAN HARIAN MONITORING BUS'];
        $rows[] = ['Tanggal: ' . $this->reportData['tanggal']];
        $rows[] = ['Total Bus: ' . $this->reportData['total_buses']];
        $rows[] = ['Total Penumpang: ' . $this->reportData['total_passengers']];
        $rows[] = [];
        $rows[] = [
            'No.',
            'Kode Bus',
            'Plat Nomor',
            'Nama Driver',
            'No. HP Driver',
            'Total Penumpang',
            'Penumpang Naik',
            'Penumpang Turun',
        ];

        foreach ($this->reportData['buses'] as $index => $bus) {
            $rows[] = [
                $index + 1,
                $bus['bus_code'],
                $bus['bus_plate'],
                $bus['driver_name'],
                $bus['driver_phone'],
                $bus['total_penumpang'],
                $bus['boarding_count'],
                $bus['alighting_count'],
            ];
        }
        return $rows;
    }
}
