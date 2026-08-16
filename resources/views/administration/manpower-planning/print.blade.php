<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manpower Planning - {{ $planning->planning_number }} - WIMS</title>
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

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 24px;
            margin-bottom: 16px;
        }

        .info-grid .label {
            font-weight: 600;
            color: #475569;
            display: inline-block;
            width: 110px;
        }

        .section-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1d4ed8;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin: 18px 0 8px;
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
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
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        .doc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 11px;
        }

        .doc-table thead th {
            background: #1e293b;
            color: #fff;
            padding: 6px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #334155;
        }

        .doc-table tbody td {
            padding: 5px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .doc-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .doc-table .text-right {
            text-align: right;
        }

        .doc-table .text-center {
            text-align: center;
        }

        .doc-table .total-row td {
            font-weight: 800;
            background: #eef2ff;
            border-top: 2px solid #1e293b;
        }

        .bottleneck {
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            font-size: 11px;
        }

        .bottleneck .bt-label {
            font-weight: 800;
            color: #92400e;
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
                size: A4 portrait;
                margin: 12mm;
            }

            .doc-table thead th {
                background: #1e293b !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .doc-table tbody tr:nth-child(even),
            .doc-table .total-row td,
            .summary-card,
            .bottleneck {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="print-container">
        @php
            $formatNumber = fn($value) => ($value == (int) $value) ? number_format((int) $value) : number_format($value, 2);
            $itemMppPerShift = fn($item) => $item->number_of_shift === 1 ? $item->required_mpp : $item->mpp_per_shift;
            $itemTotalMpp = fn($item) => $item->number_of_shift === 1 ? $item->required_mpp : ($item->mpp_per_shift * 2);
            $totalMpp = collect($divisions)->sum('total_mpp');
            $manpowerFeasible = $planning->items->every(fn($i) => $i->feasibility_status !== 'Shortage');
            $deviceFeasible = $planning->devices->every(fn($d) => $d->status === 'FEASIBLE');
        @endphp

        <div class="action-bar">
            <span class="action-title">Pratinjau Cetak — Manpower Planning {{ $planning->planning_number }}</span>
            <button type="button" class="btn-print" onclick="window.print()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z" />
                </svg>
                Cetak / Simpan PDF
            </button>
        </div>

        <div class="print-header">
            <div class="company">PT. Cipta Aneka Servis</div>
            <div class="system">Warehouse Information Management System</div>
            <div class="title">Manpower Planning</div>
        </div>

        <div class="info-grid">
            <div><span class="label">Planning No</span>: <span class="value">{{ $planning->planning_number }}</span></div>
            <div><span class="label">Planning Date</span>: <span class="value">{{ \Carbon\Carbon::parse($planning->planning_date)->translatedFormat('d F Y') }}</span></div>
            <div><span class="label">Status</span>: <span class="value">{{ $planning->status }}</span></div>
            <div><span class="label">Dibuat Oleh</span>: <span class="value">{{ $planning->creator?->name ?? '-' }}</span></div>
        </div>

        <div class="section-title">Executive Summary</div>
        <div class="summary-row">
            <div class="summary-card">
                <div class="card-label">Inbound</div>
                <div class="card-value">{{ $formatNumber($planning->inbound_volume) }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Outbound</div>
                <div class="card-value">{{ $formatNumber($planning->outbound_volume) }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">VAS</div>
                <div class="card-value">{{ $formatNumber($planning->vas_volume) }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Recommended</div>
                <div class="card-value">{{ $planning->recommendation }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Total MPP</div>
                <div class="card-value">{{ $formatNumber($planning->total_mpp) }}</div>
            </div>
            <div class="summary-card">
                <div class="card-label">Overall Status</div>
                <div class="card-value" style="color:{{ $planning->overall_status === 'CRITICAL' ? '#991b1b' : '#166534' }};">{{ $planning->overall_status }}</div>
            </div>
        </div>

        <div class="info-grid">
            <div><span class="label">Shift Duration</span>: <span class="value">{{ $formatNumber($planning->shift_duration) }} Jam</span></div>
            <div><span class="label">Non Productive</span>: <span class="value">{{ $formatNumber($planning->non_productive_hours) }} Jam</span></div>
            <div><span class="label">Effective Hours</span>: <span class="value">{{ $formatNumber($planning->effective_working_hours) }} Jam</span></div>
        </div>

        <div class="section-title">Operational Readiness</div>
        <table class="doc-table">
            <thead>
                <tr><th>Shift Recommendation</th><th>Manpower</th><th>Device</th><th>Overall</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">{{ $planning->recommendation }}</td>
                    <td class="text-center">{{ $manpowerFeasible ? '✓ FEASIBLE' : '✕ NOT FEASIBLE' }}</td>
                    <td class="text-center">{{ $deviceFeasible ? '✓ FEASIBLE' : '✕ NOT FEASIBLE' }}</td>
                    <td class="text-center">{{ $planning->overall_status === 'CRITICAL' ? '✕ NOT READY' : '✓ READY' }}</td>
                </tr>
            </tbody>
        </table>

        @if ($planning->devices->isNotEmpty())
            <div class="section-title">Device Readiness</div>
            <table class="doc-table">
                <thead>
                    <tr><th>Device</th><th>Required</th><th>Ready</th><th>Shortage</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($planning->devices as $device)
                        <tr>
                            <td class="text-center">{{ $device->device_type }}</td>
                            <td class="text-center">{{ $formatNumber($device->physical_required) }}</td>
                            <td class="text-center">{{ $formatNumber($device->ready_quantity) }}</td>
                            <td class="text-center">{{ $formatNumber($device->shortage) }}</td>
                            <td class="text-center">{{ $device->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @foreach ($divisions as $division)
            <div class="section-title">{{ $division['division'] }} Breakdown</div>
            @if ($division['reason'])
                <p style="margin:-4px 0 8px;font-size:10px;color:#1d4ed8;">Reason: {{ $division['reason'] }}</p>
            @endif
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Workload</th>
                        <th>Productivity</th>
                        <th>Eff. Hrs</th>
                        <th>MPP / Shift</th>
                        <th>Shift</th>
                        <th>Total MPP</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($division['items'] as $item)
                        <tr>
                            <td>{{ $item->name }}</td>
                            <td class="text-right">{{ $formatNumber($item->workload) }} {{ $item->workload_unit }}</td>
                            <td class="text-center">{{ $formatNumber($item->productivity_per_hour) }} {{ $item->productivity_unit }}</td>
                            <td class="text-center">{{ $formatNumber($item->effective_working_hours) }}</td>
                            <td class="text-center">{{ $formatNumber($itemMppPerShift($item)) }}</td>
                            <td class="text-center">{{ $item->number_of_shift === 0 ? '-' : $item->number_of_shift }}</td>
                            <td class="text-center">{{ $formatNumber($itemTotalMpp($item)) }}</td>
                            <td class="text-center">{{ $item->feasibility_status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        @php $hasShiftBreakdown = collect($divisions)->contains(fn($d) => ! empty($d['shift1']) || ! empty($d['shift2'])); @endphp
        @if ($hasShiftBreakdown)
            <div class="section-title">Shift Breakdown</div>
            @foreach ($divisions as $division)
                @if (! empty($division['shift1']))
                    <p style="margin-bottom:4px;font-weight:700;">{{ $division['division'] }} &mdash; Shift 1 (07:00&ndash;15:00)</p>
                    <table class="doc-table">
                        <thead><tr><th>Position</th><th>MPP</th><th>Device</th></tr></thead>
                        <tbody>
                            @foreach ($division['shift1'] as $entry)
                                <tr><td>{{ $entry['name'] }}</td><td class="text-center">{{ $formatNumber($entry['mpp']) }}</td><td class="text-center">{{ $entry['device'] ?? '-' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                @if (! empty($division['shift2']))
                    <p style="margin-bottom:4px;font-weight:700;">{{ $division['division'] }} &mdash; Shift 2 (15:00&ndash;23:00)</p>
                    <table class="doc-table">
                        <thead><tr><th>Position</th><th>MPP</th><th>Device</th></tr></thead>
                        <tbody>
                            @foreach ($division['shift2'] as $entry)
                                <tr><td>{{ $entry['name'] }}</td><td class="text-center">{{ $formatNumber($entry['mpp']) }}</td><td class="text-center">{{ $entry['device'] ?? '-' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        @endif

        @php
            $bottleneckDivisions = collect($divisions)->filter(fn($d) => ! empty($d['bottlenecks']));
            $shortageDevices = $planning->devices->filter(fn($d) => $d->shortage > 0);
            $hasBottleneck = $bottleneckDivisions->isNotEmpty() || $shortageDevices->isNotEmpty();
        @endphp
        @if ($hasBottleneck)
            <div class="section-title">Bottleneck</div>
            @foreach ($bottleneckDivisions as $division)
                <div class="bottleneck">
                    <span class="bt-label">Manpower {{ $division['division'] }}:</span> {{ implode(', ', $division['bottlenecks']) }}
                </div>
            @endforeach
            @foreach ($shortageDevices as $device)
                <div class="bottleneck">
                    <span class="bt-label">Device {{ $device->device_type }}:</span> Required {{ $formatNumber($device->physical_required) }}, Ready {{ $formatNumber($device->ready_quantity) }}, Shortage {{ $formatNumber($device->shortage) }}.
                </div>
            @endforeach
        @endif

        <div class="section-title">Total Manpower Summary</div>
        <table class="doc-table">
            <thead>
                <tr><th>Division</th><th>Min Shift</th><th>Shift</th><th>Total MPP</th></tr>
            </thead>
            <tbody>
                @foreach ($divisions as $division)
                    <tr>
                        <td>{{ $division['division'] }}</td>
                        <td class="text-center">{{ $division['minimum_shift'] }}</td>
                        <td class="text-center">{{ $division['shift'] === 0 ? 'Critical' : $division['shift'] }}</td>
                        <td class="text-center">{{ $formatNumber($division['total_mpp']) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td class="text-right">TOTAL</td>
                    <td class="text-center">—</td>
                    <td class="text-center">—</td>
                    <td class="text-center">{{ $formatNumber($totalMpp) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Manpower &amp; Device Summary</div>
        <table class="doc-table">
            <thead>
                <tr><th>Total Manpower</th><th>Total Device</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">{{ $formatNumber($planning->total_mpp) }} MPP</td>
                    <td class="text-center">{{ $formatNumber($planning->devices->sum('physical_required')) }} units</td>
                </tr>
            </tbody>
        </table>

        @if ($planning->notes)
            <div class="section-title">Notes</div>
            <p style="font-size:11px;">{{ $planning->notes }}</p>
        @endif

        <div class="signature-row">
            <div class="signature-block">
                <div class="sig-label">Disiapkan Oleh</div>
                <div class="sig-name">{{ $planning->creator?->name ?? '-' }}</div>
                <div class="sig-date">{{ $planning->created_at?->translatedFormat('d F Y') }}</div>
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
