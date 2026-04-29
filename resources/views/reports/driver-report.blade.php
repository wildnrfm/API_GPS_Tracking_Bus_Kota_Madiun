<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Operasional Harian Bus</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            margin: 24px;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 18px;
            border-bottom: 2px solid #2e7d32;
            padding-bottom: 12px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            color: #1a1a2e;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .header .subtitle {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }
        .info-box {
            background: #f5f9f0;
            border: 1px solid #c8e6c9;
            border-left: 4px solid #2e7d32;
            border-radius: 5px;
            padding: 12px 16px;
            margin-bottom: 14px;
        }
        .info-box h3 {
            font-size: 12px;
            color: #2e7d32;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .info-grid { width: 100%; }
        .info-grid tr td:first-child {
            font-weight: bold;
            color: #2e7d32;
            width: 140px;
            padding: 2px 0;
        }
        .info-grid tr td:last-child {
            padding: 2px 0;
            color: #1a1a2e;
        }
        .summary-cards {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: separate;
            border-spacing: 6px;
        }
        .summary-cards td {
            text-align: center;
            border-radius: 6px;
            padding: 10px 8px;
            border: none;
        }
        .card-green  { background: #e8f5e9; }
        .card-blue   { background: #e3f2fd; }
        .card-orange { background: #fff3e0; }
        .card-num { font-size: 24px; font-weight: bold; display: block; }
        .card-num-green  { color: #2e7d32; }
        .card-num-blue   { color: #1565c0; }
        .card-num-orange { color: #e65100; }
        .card-lbl { font-size: 10px; color: #666; display: block; margin-top: 2px; }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 6px;
            border-left: 3px solid #2e7d32;
            padding-left: 8px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.data-table thead tr { background: #2e7d32; color: white; }
        table.data-table th {
            padding: 7px 8px;
            font-size: 10px;
            font-weight: bold;
            border: 1px solid #2e7d32;
            text-align: left;
        }
        table.data-table td {
            padding: 6px 8px;
            font-size: 10px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        table.data-table tbody tr:nth-child(even) { background: #f5f5f5; }
        table.data-table tbody tr:nth-child(odd)  { background: #ffffff; }
        .text-center { text-align: center; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-yes { background: #e8f5e9; color: #2e7d32; }
        .badge-no  { background: #fff3e0; color: #e65100; }
        .no-data {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
            background: #fafafa;
            border: 1px dashed #ddd;
            border-radius: 5px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #eee;
            font-size: 9px;
            color: #aaa;
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Laporan Operasional Harian Bus</h1>
        <div class="subtitle">Sistem Informasi Bus Sekolah — Mobitra</div>
    </div>

    <div class="info-box">
        <h3>Informasi Bus &amp; Driver</h3>
        <table class="info-grid">
            <tr>
                <td>Nama Driver</td>
                <td>{{ $report['driver_name'] }}</td>
            </tr>
            <tr>
                <td>No. Telepon</td>
                <td>{{ $report['driver_phone'] }}</td>
            </tr>
            <tr>
                <td>Kode Bus</td>
                <td>{{ $report['bus_code'] }}</td>
            </tr>
            <tr>
                <td>Plat Nomor</td>
                <td>{{ $report['bus_plate'] }}</td>
            </tr>
            <tr>
                <td>Tanggal</td>
                <td>{{ \Carbon\Carbon::parse($report['tanggal'])->translatedFormat('d F Y') }}</td>
            </tr>
        </table>
    </div>

    @php
        $totalPenumpang = $report['total_penumpang'];
        $totalCheckout  = $report['penumpang_turun'];
        $belumCheckout  = $totalPenumpang - $totalCheckout;
        $completion     = $totalPenumpang > 0
                            ? round(($totalCheckout / $totalPenumpang) * 100, 1)
                            : 0;
    @endphp

    <table class="summary-cards">
        <tr>
            <td class="card-green">
                <span class="card-num card-num-green">{{ $totalPenumpang }}</span>
                <span class="card-lbl">Total Penumpang</span>
            </td>
            <td class="card-blue">
                <span class="card-num card-num-blue">{{ $totalCheckout }}</span>
                <span class="card-lbl">Sudah Checkout</span>
            </td>
            <td class="card-orange">
                <span class="card-num card-num-orange">{{ $belumCheckout }}</span>
                <span class="card-lbl">Belum Checkout</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Detail Penumpang</div>

    @if(count($report['passengers']) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">No.</th>
                <th width="20%">Nama Penumpang</th>
                <th width="12%">No. HP</th>
                <th width="16%">Halte Naik</th>
                <th width="9%">Jam Naik</th>
                <th width="9%">Jam Turun</th>
                <th width="10%">Durasi</th>
                <th width="10%">Status</th>
                <th width="14%">Koordinat Turun</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['passengers'] as $index => $passenger)
            @php
                $waktuNaik  = $passenger['waktu_naik']
                    ? \Carbon\Carbon::parse($passenger['waktu_naik'])->format('H:i')
                    : '-';
                $waktuTurun = $passenger['waktu_turun']
                    ? \Carbon\Carbon::parse($passenger['waktu_turun'])->format('H:i')
                    : '-';
                $isCheckout = $passenger['checkout'] === 'Yes';
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td><strong>{{ $passenger['nama_penumpang'] }}</strong></td>
                <td>{{ $passenger['no_telepon'] ?? '-' }}</td>
                <td>{{ $passenger['halte_naik'] }}</td>
                <td class="text-center">{{ $waktuNaik }}</td>
                <td class="text-center">{{ $waktuTurun }}</td>
                <td class="text-center">{{ $passenger['durasi_perjalanan'] ?? '-' }}</td>
                <td class="text-center">
                    @if($isCheckout)
                        <span class="badge badge-yes">Turun</span>
                    @else
                        <span class="badge badge-no">Di Bus</span>
                    @endif
                </td>
                <td class="text-center">{{ $passenger['lat_lng_turun'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="no-data">
        <p>Tidak ada data penumpang untuk tanggal ini</p>
    </div>
    @endif

    <div class="info-box" style="background:#f0fff0; border-color:#c8e6c9;">
        <h3 style="color:#2a5a2a;">Ringkasan Perjalanan</h3>
        <table class="info-grid">
            <tr>
                <td>Total Penumpang Naik</td>
                <td>{{ $report['penumpang_naik'] }} orang</td>
            </tr>
            <tr>
                <td>Total Penumpang Turun</td>
                <td>{{ $report['penumpang_turun'] }} orang</td>
            </tr>
            @if($totalPenumpang > 0)
            <tr>
                <td>Tingkat Completion</td>
                <td><strong>{{ $completion }}%</strong></td>
            </tr>
            @endif
        </table>
    </div>

    <div class="footer">
        Dicetak: {{ date('d/m/Y H:i:s') }} &nbsp;|&nbsp; Mobitra Bus Tracking System
    </div>

</body>
</html>