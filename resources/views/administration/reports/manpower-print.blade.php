<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manpower Report - {{ $filter['label'] }} - WIMS</title>
    @vite(['resources/css/app.css'])
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.35;
            padding: 16px;
        }

        .print-container { max-width: 275mm; margin: 0 auto; }

        .print-header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 12px; }
        .print-header .company { font-size: 15px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
        .print-header .system { font-size: 10px; font-weight: 600; color: #475569; margin-top: 2px; }
        .print-header .title { font-size: 13px; font-weight: 700; margin-top: 8px; text-transform: uppercase; letter-spacing: 1px; }

        .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3px 18px; margin-bottom: 10px; }
        .info-grid .label { font-weight: 600; color: #475569; }

        .summary-row { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 12px; }
        .summary-card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 8px; text-align: center; background: #f8fafc; }
        .summary-card .card-label { font-size: 8px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .3px; }
        .summary-card .card-value { font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px; }

        .section-title {
            font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
            color: #1d4ed8; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin: 14px 0 6px;
        }

        .doc-table { width: 100%; border-collapse: collapse; font-size: 9.5px; margin-bottom: 8px; }
        .doc-table thead th {
            background: #1e293b; color: #fff; padding: 4px 4px; text-align: center;
            font-weight: 600; font-size: 8.5px; text-transform: uppercase; letter-spacing: .2px;
            border: 1px solid #334155;
        }
        .doc-table tbody td { padding: 4px; border: 1px solid #e2e8f0; vertical-align: middle; }
        .doc-table tbody tr:nth-child(even) { background: #f8fafc; }
        .doc-table .text-center { text-align: center; }
        .doc-table .text-right { text-align: right; }
        .doc-table .total-row td { font-weight: 800; background: #eef2ff; border-top: 2px solid #1e293b; }
        .doc-table .worker-name { font-weight: 600; }
        .doc-table .muted { color: #64748b; font-size: 8.5px; }

        .signature-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 28px; }
        .signature-block { text-align: center; }
        .signature-block .sig-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; color: #475569; }
        .signature-block .sig-name { margin-top: 42px; font-size: 10px; font-weight: 600; border-top: 1px solid #cbd5e1; padding-top: 5px; }

        .action-bar {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;
            padding: 8px 12px; background: #f1f5f9; border-radius: 8px; border: 1px solid #e2e8f0;
        }
        .action-bar .action-title { font-size: 12px; font-weight: 600; color: #475569; }
        .btn-print {
            display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px;
            background: #1d4ed8; color: #fff; border: none; border-radius: 8px;
            font-size: 11px; font-weight: 700; cursor: pointer;
        }
        .btn-print:hover { background: #1e40af; }

        @media print {
            body { padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .action-bar { display: none !important; }
            @page { size: A4 landscape; margin: 10mm; }
            .doc-table thead th { background: #1e293b !important; color: #fff !important; }
            .doc-table tbody tr:nth-child(even), .doc-table .total-row td, .summary-card {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="print-container">
        @php
            $formatNumber = fn($value) => ($value == (int) $value) ? number_format((int) $value) : number_format($value, 2);
        @endphp

        <div class="action-bar">
            <span class="action-title">Pratinjau Cetak &mdash; Manpower Report ({{ $filter['label'] }})</span>
            <button type="button" class="btn-print" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z" />
                </svg>
                Cetak / Simpan PDF
            </button>
        </div>

        <div class="print-header">
            <div class="company">PT. Cipta Aneka Servis</div>
            <div class="system">Warehouse Information Management System</div>
            <div class="title">Manpower Report</div>
        </div>

        <div class="info-grid">
            <div><span class="label">Periode</span>: <span>{{ $filter['label'] }}</span></div>
            <div><span class="label">Role</span>: <span>{{ $roleOptions[$role] }}</span></div>
            <div><span class="label">Pencarian</span>: <span>{{ $search !== '' ? $search : 'Semua manpower' }}</span></div>
            <div><span class="label">Dicetak</span>: <span>{{ $printedAt->format('d M Y H:i') }}</span></div>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <div class="card-label">Manpower</div>
                <div class="card-value">{{ $formatNumber($summary['total_workers']) }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Hari Kerja</div>
                <div class="card-value">{{ $formatNumber($summary['total_days']) }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Durasi Kerja</div>
                <div class="card-value">{{ $formatNumber($summary['duration_hours']) }} j</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Jam Aktif</div>
                <div class="card-value">{{ $formatNumber($summary['active_hours']) }} j</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Total Orders</div>
                <div class="card-value">{{ $formatNumber($summary['total_orders']) }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Orders / Jam</div>
                <div class="card-value">{{ $summary['orders_per_hour'] !== null ? $formatNumber($summary['orders_per_hour']) : '-' }}</div>
            </div>
        </div>

        <div class="section-title">Ringkasan per Manpower</div>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Manpower</th>
                    <th>Kode</th>
                    <th>Function</th>
                    <th>Hari</th>
                    <th>Sesi</th>
                    <th>Durasi Kerja</th>
                    <th>Jam Aktif</th>
                    <th>Orders</th>
                    <th>Items</th>
                    <th>Order/Jam</th>
                    <th>Order/Hari</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($perWorker as $worker)
                    <tr>
                        <td class="worker-name">{{ $worker['name'] }}</td>
                        <td class="text-center">{{ $worker['employee_code'] ?? '-' }}</td>
                        <td class="text-center">{{ $worker['function'] }}</td>
                        <td class="text-center">{{ $worker['days'] }}</td>
                        <td class="text-center">{{ $worker['sessions'] }}</td>
                        <td class="text-center">{{ $formatNumber($worker['duration_hours']) }} j</td>
                        <td class="text-center">{{ $formatNumber($worker['active_hours']) }} j</td>
                        <td class="text-center">{{ $formatNumber($worker['orders']) }}</td>
                        <td class="text-center">{{ $formatNumber($worker['items']) }}</td>
                        <td class="text-center">{{ $worker['orders_per_hour'] !== null ? $formatNumber($worker['orders_per_hour']) : '-' }}</td>
                        <td class="text-center">{{ $worker['orders_per_day'] !== null ? $formatNumber($worker['orders_per_day']) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center">Belum ada data manpower pada periode ini.</td></tr>
                @endforelse
                @if (! empty($perWorker))
                    <tr class="total-row">
                        <td colspan="3" class="text-right">TOTAL</td>
                        <td class="text-center">{{ $formatNumber($summary['total_days']) }}</td>
                        <td class="text-center">{{ $formatNumber($summary['total_sessions']) }}</td>
                        <td class="text-center">{{ $formatNumber($summary['duration_hours']) }} j</td>
                        <td class="text-center">{{ $formatNumber($summary['active_hours']) }} j</td>
                        <td class="text-center">{{ $formatNumber($summary['total_orders']) }}</td>
                        <td class="text-center">{{ $formatNumber($summary['total_items']) }}</td>
                        <td class="text-center">{{ $summary['orders_per_hour'] !== null ? $formatNumber($summary['orders_per_hour']) : '-' }}</td>
                        <td class="text-center">-</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="section-title">Detail per Manpower per Tanggal</div>
        <table class="doc-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th style="text-align:left;">Manpower</th>
                    <th>Role</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Durasi</th>
                    <th>Jam Aktif</th>
                    <th>Orders</th>
                    <th>Items</th>
                    <th>Order/Jam</th>
                    <th>Station / RF</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-center">{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                        <td>
                            <span class="worker-name">{{ $row['name'] }}</span>
                            <div class="muted">{{ $row['employee_code'] ?? '-' }}@if ($row['username']) &middot; {{ $row['username'] }}@endif</div>
                        </td>
                        <td class="text-center" style="text-transform:capitalize;">{{ $row['role'] }}</td>
                        <td class="text-center">{{ $row['clock_in']?->format('H:i') ?? '-' }}</td>
                        <td class="text-center">{{ $row['clock_out']?->format('H:i') ?? '-' }}</td>
                        <td class="text-center">{{ $formatNumber($row['duration_hours']) }} j</td>
                        <td class="text-center">{{ $formatNumber($row['active_hours']) }} j</td>
                        <td class="text-center">{{ $formatNumber($row['orders']) }}</td>
                        <td class="text-center">{{ $formatNumber($row['items']) }}</td>
                        <td class="text-center">{{ $row['orders_per_hour'] !== null ? $formatNumber($row['orders_per_hour']) : '-' }}</td>
                        <td class="text-center">{{ $row['station'] ?: ($row['device'] ?: '-') }}</td>
                        <td class="text-center">{{ $row['is_working'] ? 'Working' : 'Selesai' }}@if (! empty($row['cross_day'])) *@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center">Belum ada data untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <p style="font-size:9px;color:#475569;margin-top:6px;">
            Durasi kerja dihitung dari clock-in sampai clock-out. Jam aktif = durasi dikurangi jeda diam lebih dari 30 menit
            (setiap jeda dihitung maksimal 30 menit). Order/Jam = orders &divide; jam aktif.
            Tanda <strong>*</strong> pada status berarti sesi tidak ditutup pada hari yang sama, sehingga durasinya dihitung
            sampai aktivitas terakhir pada hari tersebut.
        </p>

        <div class="signature-row">
            <div class="signature-block">
                <div class="sig-label">Disiapkan Oleh</div>
                <div class="sig-name">{{ $printedBy }}</div>
            </div>
            <div class="signature-block">
                <div class="sig-label">Diperiksa Oleh</div>
                <div class="sig-name"></div>
            </div>
            <div class="signature-block">
                <div class="sig-label">Disetujui Oleh</div>
                <div class="sig-name"></div>
            </div>
        </div>
    </div>
</body>

</html>
