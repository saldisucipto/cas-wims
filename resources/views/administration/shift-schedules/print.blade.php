<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shift Schedule - {{ $schedule->schedule_number }} - WIMS</title>
    @vite(['resources/css/app.css'])
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; padding: 20px; }
        .print-container { max-width: 190mm; margin: 0 auto; }
        .print-header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 12px; margin-bottom: 16px; }
        .print-header .company { font-size: 15px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; }
        .print-header .system { font-size: 11px; font-weight: 600; color: #475569; margin-top: 2px; }
        .print-header .title { font-size: 13px; font-weight: 700; margin-top: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-bottom: 16px; }
        .info-grid .label { font-weight: 600; color: #475569; display: inline-block; width: 110px; }
        .section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #1d4ed8; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin: 18px 0 8px; }
        .doc-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9px; }
        .doc-table thead th { background: #1e293b; color: #fff; padding: 6px 4px; text-align: center; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; border: 1px solid #334155; }
        .doc-table tbody td { padding: 4px; border: 1px solid #e2e8f0; text-align: center; vertical-align: middle; }
        .doc-table tbody tr:nth-child(even) { background: #f8fafc; }
        .signature-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 40px; }
        .signature-block { text-align: center; }
        .signature-block .sig-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; color: #475569; }
        .signature-block .sig-name { margin-top: 50px; font-size: 11px; font-weight: 600; border-top: 1px solid #cbd5e1; padding-top: 6px; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding: 10px 14px; background: #f1f5f9; border-radius: 8px; border: 1px solid #e2e8f0; }
        .btn-print { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; background: #1d4ed8; color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; }
        @media print {
            body { padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .action-bar { display: none !important; }
            @page { size: A4 landscape; margin: 10mm; }
            .doc-table thead th { background: #1e293b !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body>
    <div class="print-container">
        @php
            $daysInMonth = \Carbon\Carbon::create($schedule->year, $schedule->month, 1)->daysInMonth;
            $detailMap = [];
            foreach ($schedule->details as $detail) {
                $detailMap[$detail->employee_id][$detail->date] = $detail;
            }
        @endphp

        <div class="action-bar">
            <span>Pratinjau Cetak — {{ $schedule->schedule_number }}</span>
            <button type="button" class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>

        <div class="print-header">
            <div class="company">PT. Cipta Aneka Servis</div>
            <div class="system">Warehouse Information Management System</div>
            <div class="title">Monthly Core Employee Shift Schedule</div>
        </div>

        <div class="info-grid">
            <div><span class="label">Schedule No</span>: {{ $schedule->schedule_number }}</div>
            <div><span class="label">Period</span>: {{ \Carbon\Carbon::create($schedule->year, $schedule->month, 1)->translatedFormat('F Y') }}</div>
            <div><span class="label">Status</span>: {{ $schedule->status }}</div>
            <div><span class="label">Created By</span>: {{ $schedule->creator?->name ?? '-' }}</div>
        </div>

        <div class="section-title">Working Calendar</div>
        <p>Monday-Friday: Shift 1 (08:00-16:00) / Shift 2 (14:00-22:00), 7h effective &middot; Saturday: Short Shift 5h &middot; Sunday: OFF &middot; Max Weekly: 40h</p>

        <div class="section-title">Daily Employee Schedule</div>
        <p style="margin: 4px 0 8px;">S1 = Shift 1 &middot; S2 = Shift 2 &middot; S1_SAT = Saturday Short Shift 1 &middot; S2_SAT = Saturday Short Shift 2 &middot; OFF = Off &middot; LEAVE = Leave &middot; SICK = Sick &middot; PERMISSION = Permission</p>
        <table class="doc-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Employee</th>
                    @for ($d = 1; $d <= $daysInMonth; $d++) <th>{{ $d }}</th> @endfor
                </tr>
            </thead>
            <tbody>
                @foreach ($schedule->details->groupBy('employee_id') as $employeeId => $details)
                    @php $employee = $details->first()->employee; @endphp
                    <tr>
                        <td style="text-align:left;">{{ $employee?->employee_name ?? '-' }} ({{ $employee?->employee_code ?? $employeeId }})</td>
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            @php
                                $date = \Carbon\Carbon::create($schedule->year, $schedule->month, $d)->toDateString();
                                $shift = $detailMap[$employeeId][$date]->shift ?? 'OFF';
                            @endphp
                            <td>{{ $shift }}</td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (! empty($validation['coverage']))
            <div class="section-title">Manpower Coverage</div>
            <table class="doc-table">
                <thead><tr><th>Position</th><th>Shift</th><th>Required</th><th>Core</th><th>Gap</th><th>Coverage</th></tr></thead>
                <tbody>
                    @foreach ($validation['coverage'] as $row)
                        <tr><td>{{ $row['position'] }}</td><td>{{ $row['shift'] }}</td><td>{{ $row['required'] }}</td><td>{{ $row['core'] }}</td><td>{{ $row['gap'] }}</td><td>{{ $row['coverage'] }}%</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (! empty($validation['devices']))
            <div class="section-title">Device Coverage</div>
            <table class="doc-table">
                <thead><tr><th>Device</th><th>Required</th><th>Ready</th><th>Shortage</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($validation['devices'] as $row)
                        <tr><td>{{ $row['device'] }}</td><td>{{ $row['required'] }}</td><td>{{ $row['ready'] }}</td><td>{{ $row['shortage'] }}</td><td>{{ $row['status'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (! empty($validation['weekly_hours']))
            <div class="section-title">Weekly Working Hours</div>
            <table class="doc-table">
                <thead><tr><th>Employee</th>@for ($w = 1; $w <= 5; $w++) <th>W{{ $w }}</th> @endfor</tr></thead>
                <tbody>
                    @foreach ($validation['weekly_hours'] as $code => $weeksHours)
                        <tr>
                            <td style="text-align:left;">{{ $code }}</td>
                            @for ($w = 1; $w <= 5; $w++) <td>{{ $weeksHours[$w] ?? 0 }}h</td> @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="signature-row">
            <div class="signature-block"><div class="sig-label">Prepared By</div><div class="sig-name">{{ $schedule->creator?->name ?? '-' }}</div></div>
            <div class="signature-block"><div class="sig-label">Reviewed By</div><div class="sig-name"></div></div>
            <div class="signature-block"><div class="sig-label">Approved By</div><div class="sig-name"></div></div>
        </div>
    </div>
</body>

</html>
