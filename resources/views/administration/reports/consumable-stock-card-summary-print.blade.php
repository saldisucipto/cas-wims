<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Kartu Stok Consumable - WIMS</title>
    @vite(['resources/css/app.css'])
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #1e293b;
            line-height: 1.4;
            padding: 20px;
        }

        .print-container {
            max-width: 190mm;
            margin: 0 auto;
        }

        .print-header {
            text-align: center;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        .print-header .company {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .print-header .system {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-top: 2px;
        }

        .print-header .title {
            font-size: 13px;
            font-weight: 700;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-section {
            margin-bottom: 16px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 24px;
        }

        .info-grid .label {
            font-weight: 600;
            color: #475569;
            width: 110px;
            display: inline-block;
        }

        .info-grid .value {
            font-weight: 500;
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }

        .summary-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            text-align: center;
            background: #f8fafc;
        }

        .summary-card .card-label {
            font-size: 9px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .summary-card .card-value {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        .stock-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 11px;
        }

        .stock-table thead th {
            background: #1e293b;
            color: #fff;
            padding: 7px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #334155;
        }

        .stock-table tbody td {
            padding: 5px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .stock-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .stock-table .text-right {
            text-align: right;
        }

        .stock-table .text-center {
            text-align: center;
        }

        .stock-table .cell-in {
            color: #166534;
            font-weight: 600;
        }

        .stock-table .cell-out {
            color: #991b1b;
            font-weight: 600;
        }

        .stock-table .cell-balance {
            font-weight: 700;
        }

        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
            margin-top: 40px;
        }

        .signature-block {
            text-align: center;
        }

        .signature-block .sig-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #475569;
        }

        .signature-block .sig-name {
            margin-top: 50px;
            font-size: 11px;
            font-weight: 600;
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
        }

        .signature-block .sig-date {
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding: 10px 14px;
            background: #f1f5f9;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .action-bar .action-title {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: #1d4ed8;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-print:hover {
            background: #1e40af;
        }

        @media print {
            body {
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .action-bar {
                display: none !important;
            }

            @page {
                size: A4 landscape;
                margin: 12mm;
            }

            .stock-table thead th {
                background: #1e293b !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .stock-table tbody tr:nth-child(even) {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .summary-card {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="print-container">

        <div class="action-bar">
            <span class="action-title">Pratinjau Cetak - Rekap Kartu Stok Consumable</span>
            <button type="button" class="btn-print" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z" />
                </svg>
                Cetak
            </button>
        </div>

        <div class="print-header">
            <div class="company">PT. Cipta Aneka Servis</div>
            <div class="system">Warehouse Information Management System</div>
            <div class="title">Rekap Kartu Stok Consumable</div>
        </div>

        <div class="info-section">
            <div class="info-grid">
                <div><span class="label">Periode Laporan</span>: <span class="value">{{ $filter['label'] }}</span></div>
                <div><span class="label">Tanggal Cetak</span>: <span class="value">{{ $printedAt->translatedFormat('d F Y H:i') }}</span></div>
            </div>
            <div style="margin-top: 4px;"><span class="label">Dicetak Oleh</span>: <span class="value">{{ $printedBy }}</span></div>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <div class="card-label">Jumlah Jenis Consumable</div>
                <div class="card-value">{{ $rows->count() }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Total Masuk</div>
                <div class="card-value" style="color:#166534;">{{ $rows->sum('total_in') }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Total Keluar</div>
                <div class="card-value" style="color:#991b1b;">{{ $rows->sum('total_out') }}</div>
            </div>
        </div>

        <table class="stock-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Consumable</th>
                    <th>Satuan</th>
                    <th>Total Masuk</th>
                    <th>Total Keluar</th>
                    <th>Saldo Akhir</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="text-center">{{ $row['sku'] ?: '-' }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td class="text-center">{{ $row['unit'] }}</td>
                        <td class="text-right cell-in">{{ $row['total_in'] }}</td>
                        <td class="text-right cell-out">{{ $row['total_out'] }}</td>
                        <td class="text-right cell-balance">{{ $row['balance'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:16px;color:#94a3b8;">
                            Tidak ada data consumable pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="signature-row">
            <div class="signature-block">
                <div class="sig-label">Disiapkan Oleh</div>
                <div class="sig-name">{{ $printedBy }}</div>
                <div class="sig-date">{{ $printedAt->translatedFormat('d F Y') }}</div>
            </div>
            <div class="signature-block">
                <div class="sig-label">Diperiksa Oleh</div>
                <div class="sig-name"></div>
                <div class="sig-date"></div>
            </div>
            <div class="signature-block">
                <div class="sig-label">Disetujui Oleh</div>
                <div class="sig-name"></div>
                <div class="sig-date"></div>
            </div>
        </div>

    </div>
</body>

</html>
