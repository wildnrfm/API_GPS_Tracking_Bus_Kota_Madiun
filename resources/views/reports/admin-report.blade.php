<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Harian Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #1a1a2e;
            margin: 20px 24px;
            line-height: 1.5;
        }

        /* ── Header ──────────────────────────────────── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            border-bottom: 3px solid #1a1a2e;
            padding-bottom: 10px;
        }
        .header-left  { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; }
        .header h1 {
            font-size: 17px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #1a1a2e;
        }
        .header .org {
            font-size: 10px;
            color: #666;
            margin-top: 2px;
        }
        .header .tanggal-badge {
            background: #1a1a2e;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        /* ── KPI Cards (4 kotak atas) ────────────────── */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 16px;
        }
        .kpi-table td {
            border-radius: 8px;
            padding: 10px 14px;
            border: none;
            vertical-align: top;
        }
        .kpi-navy   { background: #1a1a2e; color: white; }
        .kpi-green  { background: #e8f5e9; }
        .kpi-blue   { background: #e3f2fd; }
        .kpi-red    { background: #fce4ec; }
        .kpi-orange { background: #fff3e0; }
        .kpi-num {
            font-size: 26px;
            font-weight: bold;
            display: block;
            line-height: 1.1;
        }
        .kpi-num-white  { color: #ffffff; }
        .kpi-num-green  { color: #2e7d32; }
        .kpi-num-blue   { color: #1565c0; }
        .kpi-num-red    { color: #c62828; }
        .kpi-num-orange { color: #e65100; }
        .kpi-lbl {
            font-size: 9px;
            display: block;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kpi-lbl-white  { color: rgba(255,255,255,0.75); }
        .kpi-lbl-green  { color: #388e3c; }
        .kpi-lbl-blue   { color: #1565c0; }
        .kpi-lbl-red    { color: #c62828; }
        .kpi-lbl-orange { color: #e65100; }
        .kpi-sub {
            font-size: 9px;
            margin-top: 4px;
            display: block;
        }
        .kpi-sub-white { color: rgba(255,255,255,0.6); }

        /* ── Section title ───────────────────────────── */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1a1a2e;
            border-left: 4px solid #1a1a2e;
            padding-left: 8px;
            margin-bottom: 7px;
            margin-top: 14px;
        }

        /* ── Tabel utama (rekap bus) ──────────────────── */
        table.main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 10px;
        }
        table.main-table thead tr { background: #1a1a2e; color: white; }
        table.main-table th {
            padding: 7px 7px;
            border: 1px solid #1a1a2e;
            text-align: left;
            font-size: 9.5px;
            white-space: nowrap;
        }
        table.main-table td {
            padding: 6px 7px;
            border: 1px solid #e0e0e0;
            vertical-align: middle;
        }
        table.main-table tbody tr:nth-child(even) { background: #f8f8f8; }
        table.main-table tbody tr:nth-child(odd)  { background: #ffffff; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        /* Completion bar */
        .bar-wrap {
            background: #e0e0e0;
            border-radius: 4px;
            height: 8px;
            width: 60px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 4px;
        }
        .bar-fill {
            background: #2e7d32;
            border-radius: 4px;
            height: 8px;
            display: block;
        }

        /* ── Dua kolom bawah ─────────────────────────── */
        .two-col { width: 100%; border-collapse: separate; border-spacing: 10px; }
        .two-col td { vertical-align: top; border: none; padding: 0; }

        /* ── Halte stats ─────────────────────────────── */
        .halte-box {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 12px;
        }
        .halte-box h4 {
            font-size: 11px;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 8px;
            border-left: 3px solid #1a1a2e;
            padding-left: 6px;
        }
        .halte-row { margin-bottom: 6px; }
        .halte-name { font-size: 10px; color: #333; margin-bottom: 2px; }
        .halte-bar-wrap {
            background: #ddd;
            border-radius: 3px;
            height: 7px;
            width: 100%;
        }
        .halte-bar-fill {
            background: #1a1a2e;
            border-radius: 3px;
            height: 7px;
            display: block;
        }
        .halte-count { font-size: 9px; color: #666; margin-top: 1px; }

        /* ── Catatan driver ──────────────────────────── */
        .catatan-box {
            background: #fffde7;
            border: 1px solid #f9a825;
            border-left: 4px solid #f9a825;
            border-radius: 8px;
            padding: 12px;
        }
        .catatan-box h4 {
            font-size: 11px;
            font-weight: bold;
            color: #f57f17;
            margin-bottom: 8px;
        }
        .catatan-item {
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #ffe082;
        }
        .catatan-item:last-child { border-bottom: none; margin-bottom: 0; }
        .catatan-bus { font-weight: bold; font-size: 10px; color: #1a1a2e; }
        .catatan-text { font-size: 10px; color: #555; margin-top: 2px; }

        /* ── No data ──────────────────────────────────── */
        .no-data {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
            background: #fafafa;
            border: 1px dashed #ddd;
            border-radius: 6px;
        }

        /* ── Footer ───────────────────────────────────── */
        .footer {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #eee;
            font-size: 9px;
            color: #aaa;
            display: table;
            width: 100%;
        }
        .footer-left  { display: table-cell; }
        .footer-right { display: table-cell; text-align: right; }
    </style>
</head>
<body>

{{-- ── Header ──────────────────────────────────────── --}}
<div class="header">
    <div class="header-left">
        <h1>Laporan Monitoring Operasional Bus</h1>
        <div class="org">Sistem Informasi Bus Sekolah — Mobitra &nbsp;|&nbsp; Kota Madiun</div>
    </div>
    <div class="header-right">
        <span class="tanggal-badge">
            {{ \Carbon\Carbon::parse($report['tanggal'])->translatedFormat('d F Y') }}
        </span>
    </div>
</div>

{{-- ── KPI Cards ─────────────────────────────────────── --}}
@php
    $totalBus       = $report['total_buses'];
    $totalNaik      = $report['total_passengers'];
    $totalTurun     = $report['total_checkout'];
    $belumTurun     = $report['total_belum_turun'];
    $completion     = $report['completion_rate'];
@endphp

<table class="kpi-table">
    <tr>
        <td class="kpi-navy">
            <span class="kpi-num kpi-num-white">{{ $totalBus }}</span>
            <span class="kpi-lbl kpi-lbl-white">Bus Beroperasi</span>
            <span class="kpi-sub kpi-sub-white">unit aktif hari ini</span>
        </td>
        <td class="kpi-green">
            <span class="kpi-num kpi-num-green">{{ $totalNaik }}</span>
            <span class="kpi-lbl kpi-lbl-green">Total Penumpang Naik</span>
        </td>
        <td class="kpi-blue">
            <span class="kpi-num kpi-num-blue">{{ $totalTurun }}</span>
            <span class="kpi-lbl kpi-lbl-blue">Sudah Check-out</span>
        </td>
        <td class="kpi-red">
            <span class="kpi-num kpi-num-red">{{ $belumTurun }}</span>
            <span class="kpi-lbl kpi-lbl-red">Belum Check-out</span>
        </td>
        <td class="kpi-orange">
            <span class="kpi-num kpi-num-orange">{{ $completion }}%</span>
            <span class="kpi-lbl kpi-lbl-orange">Completion Rate</span>
        </td>
    </tr>
</table>

{{-- ── Tabel Rekap Per Bus ──────────────────────────── --}}
<div class="section-title">Rekap Operasional Per Bus</div>

@if(count($report['buses']) > 0)
<table class="main-table">
    <thead>
        <tr>
            <th width="3%">No</th>
            <th width="8%">Kode Bus</th>
            <th width="9%">Plat</th>
            <th width="17%">Driver</th>
            <th width="10%">No. HP</th>
            <th width="6%">Naik</th>
            <th width="6%">Turun</th>
            <th width="6%">Sisa</th>
            <th width="13%">Completion</th>
            <th width="8%">Mulai</th>
            <th width="8%">Selesai</th>
            <th width="8%">Durasi</th>
            <th width="9%">Rata Kecepatan</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report['buses'] as $i => $bus)
        @php
            $comp = $bus['boarding_count'] > 0
                ? round(($bus['alighting_count'] / $bus['boarding_count']) * 100)
                : 0;
            $barW = min($comp, 100);
        @endphp
        <tr>
            <td class="text-center">{{ $i + 1 }}</td>
            <td><strong>{{ $bus['bus_code'] }}</strong></td>
            <td>{{ $bus['bus_plate'] }}</td>
            <td>{{ $bus['driver_name'] }}</td>
            <td>{{ $bus['driver_phone'] }}</td>
            <td class="text-center"><strong>{{ $bus['boarding_count'] }}</strong></td>
            <td class="text-center">{{ $bus['alighting_count'] }}</td>
            <td class="text-center"
                style="color:{{ $bus['belum_turun'] > 0 ? '#c62828' : '#2e7d32' }}; font-weight:bold;">
                {{ $bus['belum_turun'] }}
            </td>
            <td class="text-center">
                <div class="bar-wrap">
                    <div class="bar-fill" style="width:{{ $barW }}%"></div>
                </div>
                {{ $comp }}%
            </td>
            <td class="text-center">{{ $bus['waktu_mulai'] }}</td>
            <td class="text-center">{{ $bus['waktu_selesai'] }}</td>
            <td class="text-center">{{ $bus['durasi_operasi'] }}</td>
            <td class="text-center">{{ $bus['avg_speed'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<div class="no-data">Tidak ada bus beroperasi untuk tanggal ini</div>
@endif

{{-- ── Halte Tersibuk + Catatan Driver ─────────────── --}}
@php
    $hasCatatan = collect($report['buses'])->filter(fn($b) => $b['catatan'] && $b['catatan'] !== '-')->isNotEmpty();
    $hasHalte   = !empty($report['halte_stats']);
    $maxHalte   = !empty($report['halte_stats']) ? $report['halte_stats'][0]['count'] : 1;
@endphp

<table class="two-col">
    <tr>
        {{-- Halte Tersibuk --}}
        <td width="48%">
            @if($hasHalte)
            <div class="halte-box">
                <h4>5 Halte Tersibuk Hari Ini</h4>
                @foreach($report['halte_stats'] as $h)
                @php $pct = $maxHalte > 0 ? round(($h['count'] / $maxHalte) * 100) : 0; @endphp
                <div class="halte-row">
                    <div class="halte-name">{{ $h['nama'] }}</div>
                    <div class="halte-bar-wrap">
                        <div class="halte-bar-fill" style="width:{{ $pct }}%"></div>
                    </div>
                    <div class="halte-count">{{ $h['count'] }} penumpang</div>
                </div>
                @endforeach
            </div>
            @endif
        </td>

        {{-- Catatan Driver --}}
        <td width="48%">
            @if($hasCatatan)
            <div class="catatan-box">
                <h4>Catatan Driver</h4>
                @foreach($report['buses'] as $bus)
                    @if($bus['catatan'] && $bus['catatan'] !== '-')
                    <div class="catatan-item">
                        <div class="catatan-bus">{{ $bus['bus_code'] }} — {{ $bus['driver_name'] }}</div>
                        <div class="catatan-text">{{ $bus['catatan'] }}</div>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif
        </td>
    </tr>
</table>

{{-- ── Footer ────────────────────────────────────────── --}}
<div class="footer">
    <div class="footer-left">Laporan ini digenerate otomatis — hanya untuk keperluan internal</div>
    <div class="footer-right">Dicetak: {{ date('d/m/Y H:i:s') }} &nbsp;|&nbsp; Mobitra Bus Tracking System</div>
</div>

</body>
</html>