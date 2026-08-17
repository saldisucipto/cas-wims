@extends('layouts.operation')

@section('title', 'Shift Schedule - WIMS')

@section('content')
    @php
        $daysInMonth = \Carbon\Carbon::create($schedule->year, $schedule->month, 1)->daysInMonth;
        $detailMap = [];
        foreach ($schedule->details as $detail) {
            $detailMap[$detail->employee_id][$detail->date] = $detail;
        }
        $badge = [
            'S1' => 'bg-blue-100 text-blue-700',
            'S2' => 'bg-violet-100 text-violet-700',
            'S1_SAT' => 'bg-amber-100 text-amber-700',
            'S2_SAT' => 'bg-orange-100 text-orange-700',
            'OFF' => 'bg-slate-200 text-slate-700',
            'LEAVE' => 'bg-red-100 text-red-700',
            'SICK' => 'bg-rose-100 text-rose-700',
            'PERMISSION' => 'bg-teal-100 text-teal-700',
        ];
        $toMinutes = function (?string $t): ?int {
            if (! $t) { return null; }
            [$h, $m] = array_map('intval', explode(':', $t));
            return $h * 60 + $m;
        };
        $axisStart = 8 * 60; $axisEnd = 22 * 60; $axisSpan = $axisEnd - $axisStart;
        $leftPct = fn (?string $t) => (($toMinutes($t) - $axisStart) / $axisSpan) * 100;
        $widthPct = fn (?string $s, ?string $e) => (($toMinutes($e) - $toMinutes($s)) / $axisSpan) * 100;
    @endphp

    <main class="mx-auto w-full max-w-[100rem] px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Shift Schedule</p>
                    <h1 class="wims-page-title">{{ $schedule->schedule_number }}</h1>
                    <p class="wims-page-subtitle">{{ \Carbon\Carbon::create($schedule->year, $schedule->month, 1)->translatedFormat('F Y') }}</p>
                    <p class="wims-breadcrumb">Administration / Scheduling / Shift Schedule</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($schedule->status !== 'FINAL')
                        <a href="{{ route('administration.shift-schedules.edit', $schedule) }}" class="wims-btn wims-btn-primary">Edit Assignments</a>
                        <form action="{{ route('administration.shift-schedules.regenerate', $schedule) }}" method="POST">
                            @csrf
                            <button type="submit" class="wims-btn wims-btn-warning">Regenerate</button>
                        </form>
                        <form action="{{ route('administration.shift-schedules.finalize', $schedule) }}" method="POST" onsubmit="return confirm('Finalize this schedule?');">
                            @csrf
                            <button type="submit" class="wims-btn wims-btn-success">Finalize</button>
                        </form>
                    @endif
                    <a href="{{ route('administration.shift-schedules.handover', $schedule) }}" class="wims-btn wims-btn-primary">Handover / Outstanding Jobs</a>
                    <form action="{{ route('administration.shift-schedules.duplicate', $schedule) }}" method="POST">
                        @csrf
                        <button type="submit" class="wims-btn wims-btn-primary">Duplicate</button>
                    </form>
                    <a href="{{ route('administration.shift-schedules.print', $schedule) }}" target="_blank" class="wims-btn wims-btn-primary">Print / PDF</a>
                    <a href="{{ route('administration.shift-schedules') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            @if (session('error'))
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</p>
            @endif

            <div class="mt-5 grid grid-cols-2 gap-4 md:grid-cols-5">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Status</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $schedule->status }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Core Employees</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $validation['summary']['total_employees'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Shift 1 / Shift 2</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $validation['summary']['shift_1'] }} / {{ $validation['summary']['shift_2'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Sat S1 / Sat S2</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $validation['summary']['shift_1_sat'] }} / {{ $validation['summary']['shift_2_sat'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Weekly Violations</p>
                    <p class="mt-1 text-lg font-bold {{ $validation['summary']['weekly_violations'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $validation['summary']['weekly_violations'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Off / Leave / Sick / Permission</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $validation['summary']['off'] }} / {{ $validation['summary']['leave'] }} / {{ $validation['summary']['sick'] }} / {{ $validation['summary']['permission'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Overall</p>
                    <p class="mt-1 text-lg font-bold {{ $validation['overall_status'] === 'READY' ? 'text-emerald-600' : 'text-red-600' }}">{{ $validation['overall_status'] }}</p>
                </article>
            </div>

            @if (! empty($validation['errors']))
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Schedule has {{ count($validation['errors']) }} validation issue(s):</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach (array_slice($validation['errors'], 0, 20) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! empty($validation['weekly_violations']))
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="font-semibold">Weekly working-hour warnings:</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($validation['weekly_violations'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6">
                <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Shift Timeline</h3>
                <p class="text-sm text-slate-600">Coverage Shift 1, Shift 2, overlap 14:00–16:00, break, dan Saturday short shift.</p>

                @php $ticks = ['08:00','10:00','12:00','14:00','16:00','18:00','20:00','22:00']; @endphp
                <div class="mt-3 rounded-xl border border-slate-200 bg-white p-4">
                    <div class="relative ml-32">
                        <div class="relative h-6 border-b border-slate-300 text-[10px] text-slate-500">
                            @foreach ($ticks as $tick)
                                <span class="absolute -translate-x-1/2" style="left: {{ $leftPct($tick) }}%">{{ $tick }}</span>
                            @endforeach
                        </div>

                        @php $timelineDefs = $timeline['definitions']; @endphp
                        @foreach (['S1' => 'Shift 1', 'S2' => 'Shift 2'] as $code => $label)
                            @if (isset($timelineDefs[$code]))
                                @php $def = $timelineDefs[$code]; @endphp
                                <div class="mt-2 flex items-center gap-2">
                                    <div class="w-28 shrink-0 text-xs font-semibold text-slate-600">{{ $label }}</div>
                                    <div class="relative h-7 flex-1 rounded bg-slate-100">
                                        <div class="absolute top-0 h-full rounded {{ $code === 'S1' ? 'bg-blue-400' : 'bg-violet-400' }} opacity-80"
                                            style="left: {{ $leftPct($def['start_time']) }}%; width: {{ $widthPct($def['start_time'], $def['end_time']) }}%"></div>
                                        @if ($def['break_start'] && $def['break_end'])
                                            <div class="absolute top-1 h-5 rounded border border-dashed border-slate-400 bg-white/70"
                                                style="left: {{ $leftPct($def['break_start']) }}%; width: {{ $widthPct($def['break_start'], $def['break_end']) }}%" title="Break {{ $def['break_start'] }}-{{ $def['break_end'] }}"></div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @if ($timeline['overlap'])
                            <div class="mt-2 flex items-center gap-2">
                                <div class="w-28 shrink-0 text-xs font-semibold text-slate-600">Overlap</div>
                                <div class="relative h-7 flex-1 rounded bg-slate-100">
                                    <div class="absolute top-0 h-full rounded bg-emerald-400 opacity-80"
                                        style="left: {{ $leftPct($timeline['overlap']['start']) }}%; width: {{ $widthPct($timeline['overlap']['start'], $timeline['overlap']['end']) }}%"></div>
                                </div>
                            </div>
                        @endif

                        <div class="mt-4 border-t border-slate-200 pt-2 text-xs text-slate-600">
                            <span class="font-semibold">Saturday Short Shift:</span>
                            @foreach (['S1_SAT' => 'Sat S1', 'S2_SAT' => 'Sat S2'] as $code => $label)
                                @if (isset($timelineDefs[$code]))
                                    <span class="ml-2 inline-block">{{ $label }} {{ $timelineDefs[$code]['start_time'] }}–{{ $timelineDefs[$code]['end_time'] }} ({{ $timelineDefs[$code]['effective_hours'] }}h)</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Daily Employee Schedule</h3>
                <div class="mt-2 flex flex-wrap gap-1.5 text-xs text-slate-600">
                    <span class="font-semibold">Keterangan:</span>
                    @foreach ($shifts as $code => $label)
                        <span class="inline-flex rounded-full px-2 py-0.5 font-semibold {{ $badge[$code] ?? 'bg-slate-100 text-slate-700' }}">{{ $code }} = {{ $label }}</span>
                    @endforeach
                </div>
                <div class="mt-2 overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th class="min-w-[10rem]">Employee</th>
                                @for ($day = 1; $day <= $daysInMonth; $day++)
                                    <th class="text-center">D{{ $day }}</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $entry)
                                @php $employee = $entry->employee; @endphp
                                <tr>
                                    <td class="font-semibold text-slate-900">
                                        <div>{{ $employee?->employee_name ?? '-' }}</div>
                                        <div class="text-xs font-normal text-slate-500">{{ $employee?->employee_code ?? $entry->employee_id }}</div>
                                    </td>
                                    @for ($day = 1; $day <= $daysInMonth; $day++)
                                        @php
                                            $date = \Carbon\Carbon::create($schedule->year, $schedule->month, $day)->toDateString();
                                            $detail = $detailMap[$entry->employee_id][$date] ?? null;
                                            $shift = $detail?->shift ?? 'OFF';
                                            $cls = $badge[$shift] ?? 'bg-slate-200 text-slate-700';
                                        @endphp
                                        <td class="text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $cls }}">{{ $shift }}</span>
                                        </td>
                                    @endfor
                                </tr>
                            @empty
                                <tr><td colspan="{{ $daysInMonth + 1 }}"><div class="wims-empty-state">Belum ada detail schedule.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Daily Manpower Coverage</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="wims-table min-w-full text-left text-sm">
                            <thead><tr><th>Date</th><th>S1</th><th>S2</th><th>Sat S1</th><th>Sat S2</th><th>Off</th><th>Leave</th><th>Sick</th><th>Perm</th></tr></thead>
                            <tbody>
                                @forelse ($validation['daily_coverage'] as $date => $counts)
                                    <tr>
                                        <td class="font-semibold">{{ $date }}</td>
                                        <td>{{ $counts['S1'] ?? 0 }}</td>
                                        <td>{{ $counts['S2'] ?? 0 }}</td>
                                        <td>{{ $counts['S1_SAT'] ?? 0 }}</td>
                                        <td>{{ $counts['S2_SAT'] ?? 0 }}</td>
                                        <td>{{ $counts['OFF'] ?? 0 }}</td>
                                        <td>{{ $counts['LEAVE'] ?? 0 }}</td>
                                        <td>{{ $counts['SICK'] ?? 0 }}</td>
                                        <td>{{ $counts['PERMISSION'] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9"><div class="wims-empty-state">Belum ada data.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Weekly Working Hours</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="wims-table min-w-full text-left text-sm">
                            <thead><tr><th>Employee</th>@for ($w = 1; $w <= 5; $w++) <th>W{{ $w }}</th> @endfor</tr></thead>
                            <tbody>
                                @forelse ($validation['weekly_hours'] as $code => $weeks)
                                    <tr>
                                        <td class="font-semibold">{{ $code }}</td>
                                        @for ($w = 1; $w <= 5; $w++)
                                            <td>{{ $weeks[$w] ?? 0 }}h</td>
                                        @endfor
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><div class="wims-empty-state">Belum ada data.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (! empty($validation['exceeding_weekly']))
                        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <p class="font-semibold">Employees exceeding weekly limit:</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($validation['exceeding_weekly'] as $row)
                                    <li>{{ $row['employee'] }} week {{ $row['week'] }}: {{ $row['hours'] }}h {{ $row['overridden'] ? '(overridden)' : '' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @if (! empty($validation['coverage']))
                <div class="mt-6">
                    <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Manpower Coverage</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="wims-table min-w-full text-left text-sm">
                            <thead><tr><th>Position</th><th>Shift</th><th>Required</th><th>Core</th><th>Daily Worker Gap</th><th>Coverage</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($validation['coverage'] as $row)
                                    <tr>
                                        <td>{{ $row['position'] }}</td>
                                        <td>{{ $row['shift'] }}</td>
                                        <td>{{ $row['required'] }}</td>
                                        <td>{{ $row['core'] }}</td>
                                        <td>{{ $row['gap'] }}</td>
                                        <td>{{ $row['coverage'] }}%</td>
                                        <td>{{ $row['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (! empty($validation['devices']))
                <div class="mt-6">
                    <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Device Coverage</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="wims-table min-w-full text-left text-sm">
                            <thead><tr><th>Device</th><th>Shift</th><th>Required</th><th>Ready</th><th>Shortage</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($validation['devices'] as $row)
                                    <tr>
                                        <td class="font-semibold">{{ $row['device'] }}</td>
                                        <td>{{ $row['shift'] }}</td>
                                        <td>{{ $row['required'] }}</td>
                                        <td>{{ $row['ready'] }}</td>
                                        <td>{{ $row['shortage'] }}</td>
                                        <td>{{ $row['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </main>
@endsection
