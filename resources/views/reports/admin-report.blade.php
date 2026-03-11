<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Harian Admin</title>
    @push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
    @endpush
</head>
<body>
    <div class="header">
        <h1>Laporan Harian Monitoring Bus</h1>
        <p>PT. Diskominfo Kota Madiun</p>
    </div>

    <div class="info-box">
        <p><strong>Tanggal:</strong> {{ date('d/m/Y', strtotime($report['tanggal'])) }}</p>
        <p><strong>Periode:</strong> {{ date('l, d F Y', strtotime($report['tanggal'])) }}</p>
        <p><strong>Total Bus Beroperasi:</strong> {{ $report['total_buses'] }}</p>
        <p><strong>Total Penumpang:</strong> {{ $report['total_passengers'] }} orang</p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">No.</th>
                <th width="12%">Kode Bus</th>
                <th width="12%">Plat Nomor</th>
                <th width="18%">Nama Driver</th>
                <th width="13%">No. HP Driver</th>
                <th width="12%">Total Penumpang</th>
                <th width="12%">Check-in</th>
                <th width="12%">Check-out</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['buses'] as $index => $bus)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $bus['bus_code'] }}</strong></td>
                <td>{{ $bus['bus_plate'] }}</td>
                <td>{{ $bus['driver_name'] }}</td>
                <td>{{ $bus['driver_phone'] }}</td>
                <td class="text-center"><strong>{{ $bus['total_penumpang'] }}</strong></td>
                <td class="text-center">{{ $bus['boarding_count'] }}</td>
                <td class="text-center">{{ $bus['alighting_count'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <p>RINGKASAN :</p>
        <p>✓ Total Bus: {{ $report['total_buses'] }} unit</p>
        <p>✓ Total Penumpang: {{ $report['total_passengers'] }} orang</p>
        <p>✓ Rata-rata Penumpang per Bus: {{ round($report['total_passengers'] / max(1, $report['total_buses']), 2) }} orang</p>
    </div>

    <div class="footer">
        <p>Laporan ini dicetak otomatis oleh Sistem Bus Tracking</p>
        <p>{{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
