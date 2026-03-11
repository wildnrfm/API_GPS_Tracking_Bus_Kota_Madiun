<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Perjalanan Bus</title>
    @push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
    @endpush
</head>
<body>
    <div class="header">
        <h1>Laporan Perjalanan Bus - Detail Penumpang</h1>
        <p>PT. Diskominfo Kota Madiun</p>
    </div>

    <div class="bus-info">
        <h3>Informasi Bus & Driver</h3>
        <div class="bus-info-row">
            <p><strong>Kode Bus:</strong> {{ $report['bus_code'] }}</p>
            <p><strong>Plat Nomor:</strong> {{ $report['bus_plate'] }}</p>
            <p><strong>Tanggal:</strong> {{ date('d/m/Y', strtotime($report['tanggal'])) }}</p>
        </div>
        <div class="bus-info-row">
            <p><strong>Nama Driver:</strong> {{ $report['driver_name'] }}</p>
            <p><strong>No. HP:</strong> {{ $report['driver_phone'] }}</p>
            <p><strong>Total Penumpang:</strong> <strong style="color: #d9534f;">{{ $report['total_penumpang'] }} orang</strong></p>
        </div>
    </div>

    @if(count($report['passengers']) > 0)
    <table>
        <thead>
            <tr>
                <th width="4%">No.</th>
                <th width="16%">Nama Penumpang</th>
                <th width="12%">No. HP</th>
                <th width="15%">Halte Naik</th>
                <th width="8%">Jam Naik</th>
                <th width="8%">Jam Turun</th>
                <th width="10%">Durasi</th>
                <th width="10%">Lat Turun</th>
                <th width="10%">Lng Turun</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['passengers'] as $index => $passenger)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $passenger['student_name'] }}</strong></td>
                <td>{{ $passenger['student_phone'] }}</td>
                <td>{{ $passenger['halte_naik'] }}</td>
                <td class="text-center">{{ $passenger['waktu_naik'] }}</td>
                <td class="text-center">{{ $passenger['waktu_turun'] }}</td>
                <td class="text-center">{{ $passenger['durasi_perjalanan'] }}</td>
                <td class="text-center">{{ $passenger['lat_turun'] }}</td>
                <td class="text-center">{{ $passenger['lng_turun'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        <p>Tidak ada data perjalanan untuk tanggal ini</p>
    </div>
    @endif

    <div class="summary">
        <p><strong>RINGKASAN PERJALANAN:</strong></p>
        <p>✓ Total Penumpang Naik: {{ $report['penumpang_naik'] }} orang</p>
        <p>✓ Total Penumpang Turun: {{ $report['penumpang_turun'] }} orang</p>
        @if($report['total_penumpang'] > 0)
        <p>✓ Tingkat Completion: {{ round(($report['penumpang_turun'] / $report['total_penumpang']) * 100, 1) }}%</p>
        @endif
    </div>

    <div class="footer">
        <p>Laporan ini dicetak otomatis oleh Sistem Bus Tracking</p>
        <p>{{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
