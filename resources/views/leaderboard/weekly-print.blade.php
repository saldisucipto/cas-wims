<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weekly Productivity Rewards - WIMS</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; color: #172033; font: 11px/1.35 Arial, sans-serif; }
        .page { max-width: 1100px; margin: 0 auto; }
        .actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 9px 12px; border: 1px solid #dbe3ef; border-radius: 7px; background: #f8fafc; }
        .actions strong { color: #475569; }
        button { border: 0; border-radius: 6px; padding: 7px 14px; color: #fff; background: #1d4ed8; font-weight: 700; cursor: pointer; }
        .header { padding-bottom: 10px; border-bottom: 2px solid #172033; text-align: center; }
        .company { font-size: 16px; font-weight: 800; text-transform: uppercase; }
        .system { margin-top: 2px; color: #64748b; font-size: 10px; }
        .title { margin-top: 8px; font-size: 14px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
        .period { margin: 10px 0 14px; text-align: center; color: #475569; font-weight: 700; }
        .section { margin-top: 18px; break-inside: avoid; }
        .section h2 { margin: 0 0 7px; padding-bottom: 4px; border-bottom: 1px solid #cbd5e1; color: #1d4ed8; font-size: 11px; text-transform: uppercase; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 9px; }
        .card { padding: 7px; border: 1px solid #cbd5e1; border-radius: 5px; background: #f8fafc; text-align: center; }
        .card small { display: block; color: #64748b; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .card strong { display: block; margin-top: 2px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9.5px; }
        th { padding: 5px 4px; border: 1px solid #334155; background: #1e293b; color: #fff; font-size: 8.5px; text-transform: uppercase; }
        td { padding: 5px 4px; border: 1px solid #dbe3ef; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .center { text-align: center; }
        .right { text-align: right; }
        .winner { background: #fffbeb !important; font-weight: 700; }
        .note { color: #64748b; font-size: 9px; }
        @media print {
            body { padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .actions { display: none; }
            @page { size: A4 landscape; margin: 10mm; }
            th { background: #1e293b !important; color: #fff !important; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="actions">
            <strong>Pratinjau Weekly Productivity Rewards</strong>
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>

        <div class="header">
            <div class="company">PT. Cipta Aneka Servis</div>
            <div class="system">Warehouse Information Management System</div>
            <div class="title">Weekly Productivity Rewards</div>
        </div>
        <div class="period">Periode: {{ $weekStart->translatedFormat('d F Y') }} - {{ $weekEnd->translatedFormat('d F Y') }} &middot; Dicetak {{ $generatedAt->format('d M Y H:i') }}</div>

        @forelse ($sections as $section)
            <div class="section">
                <h2>{{ $section['title'] }}</h2>
                <div class="summary">
                    <div class="card"><small>Total Workers</small><strong>{{ number_format($section['totals']['workers']) }}</strong></div>
                    <div class="card"><small>Total Orders</small><strong>{{ number_format($section['totals']['orders']) }}</strong></div>
                    <div class="card"><small>Total Items</small><strong>{{ number_format($section['totals']['items']) }}</strong></div>
                    <div class="card"><small>Total Jam Aktif</small><strong>{{ number_format($section['totals']['active_hours'], 2) }} j</strong></div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Rank</th><th style="text-align:left">Worker</th><th>Function</th><th>Hari</th><th>Sesi</th><th>Orders</th><th>Items</th><th>Jam Aktif</th><th>Order/Jam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($section['rows'] as $row)
                            <tr class="{{ $row['rank'] <= 3 ? 'winner' : '' }}">
                                <td class="center">{{ $row['rank'] }}</td>
                                <td><strong>{{ $row['name'] }}</strong><br><span class="note">{{ $row['employee_code'] ?? '-' }}{{ $row['username'] ? ' · '.$row['username'] : '' }}</span></td>
                                <td class="center">{{ $row['function'] }}</td>
                                <td class="center">{{ $row['days_worked'] }}</td>
                                <td class="center">{{ $row['sessions'] }}</td>
                                <td class="right">{{ number_format($row['orders']) }}</td>
                                <td class="right">{{ number_format($row['items']) }}</td>
                                <td class="right">{{ number_format($row['active_hours'], 2) }}</td>
                                <td class="right">{{ $row['orders_per_hour'] !== null ? number_format($row['orders_per_hour'], 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="center">Belum ada output pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <h2>Detail Productivity Harian</h2>
                <table>
                    <thead>
                        <tr><th>Tanggal</th><th style="text-align:left">Worker</th><th>Role</th><th>Orders</th><th>Items</th><th>Jam Aktif</th><th>Order/Jam</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($section['daily_rows'] as $row)
                            <tr>
                                <td class="center">{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="center">{{ $row['role'] }}</td>
                                <td class="right">{{ number_format($row['orders']) }}</td>
                                <td class="right">{{ number_format($row['items']) }}</td>
                                <td class="right">{{ number_format($row['active_hours'], 2) }}</td>
                                <td class="right">{{ $row['orders_per_hour'] !== null ? number_format($row['orders_per_hour'], 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="center">Tidak ada detail harian.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @empty
            <p class="period">Tidak ada data productivity.</p>
        @endforelse

        <p class="note">Ranking ditentukan dari total orders selama satu minggu. Items menjadi tie-breaker. Dokumen ini dapat digunakan sebagai dasar proses rewards mingguan.</p>
    </div>
</body>
</html>
