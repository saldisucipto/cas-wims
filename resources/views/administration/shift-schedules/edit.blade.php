@extends('layouts.operation')

@section('title', 'Edit Assignments - WIMS')

@section('content')
    @php
        $detailMap = [];
        foreach ($schedule->details as $detail) {
            $detailMap[$detail->employee_id][$detail->date] = $detail;
        }
    @endphp

    <main class="mx-auto w-full max-w-[100rem] px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Shift Schedule</p>
                    <h1 class="wims-page-title">Edit Assignments — {{ $schedule->schedule_number }}</h1>
                    <p class="wims-page-subtitle">{{ \Carbon\Carbon::create($schedule->year, $schedule->month, 1)->translatedFormat('F Y') }} · Set shift per employee per day, individually or in bulk.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('administration.shift-schedules.show', $schedule) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            @if ($schedule->status === 'FINAL')
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">This schedule is finalized and read-only.</p>
            @endif

            <form action="{{ route('administration.shift-schedules.assignments', $schedule) }}" method="POST" class="mt-6">
                @csrf
                <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-semibold text-slate-700">Legend:</span>
                    @foreach ($shifts as $code => $label)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $code }} = {{ $label }}</span>
                    @endforeach
                </div>

                <div class="overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th class="min-w-[10rem]">Employee</th>
                                @for ($day = 1; $day <= $days; $day++)
                                    <th class="text-center">
                                        <div>D{{ $day }}</div>
                                        <select class="day-bulk mt-1 w-24 rounded border border-slate-300 px-1 py-0.5 text-xs" data-day="{{ $day }}">
                                            <option value="">Set day…</option>
                                            @foreach ($shifts as $code => $label)
                                                <option value="{{ $code }}">{{ $code }}</option>
                                            @endforeach
                                        </select>
                                    </th>
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
                                        <select class="row-bulk mt-1 w-24 rounded border border-slate-300 px-1 py-0.5 text-xs" data-employee="{{ $entry->employee_id }}">
                                            <option value="">Set row…</option>
                                            @foreach ($shifts as $code => $label)
                                                <option value="{{ $code }}">{{ $code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    @for ($day = 1; $day <= $days; $day++)
                                        @php
                                            $date = \Carbon\Carbon::create($schedule->year, $schedule->month, $day)->toDateString();
                                            $detail = $detailMap[$entry->employee_id][$date] ?? null;
                                        @endphp
                                        <td class="text-center">
                                            @if ($detail)
                                                <select name="assignments[{{ $detail->id }}]"
                                                    class="cell-select w-24 rounded border border-slate-300 px-1 py-1 text-xs"
                                                    data-employee="{{ $entry->employee_id }}" data-day="{{ $day }}">
                                                    @foreach ($shifts as $code => $label)
                                                        <option value="{{ $code }}" @selected($detail->shift === $code)>{{ $code }}</option>
                                                    @endforeach
                                                </select>
                                                <label class="mt-1 block text-[10px] text-slate-500" title="Override weekly hour limit">
                                                    <input type="checkbox" name="overrides[{{ $detail->id }}]" value="1" @checked($detail->is_override)> override
                                                </label>
                                            @else
                                                <span class="text-xs text-slate-400">–</span>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            @empty
                                <tr><td colspan="{{ $days + 1 }}"><div class="wims-empty-state">Belum ada detail schedule.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="wims-btn wims-btn-primary" @disabled($schedule->status === 'FINAL')>Save Assignments</button>
                    <a href="{{ route('administration.shift-schedules.show', $schedule) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</a>
                </div>
            </form>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            $('.row-bulk').on('change', function() {
                const value = $(this).val();
                const employee = $(this).data('employee');
                if (!value) return;
                $(`.cell-select[data-employee="${employee}"]`).val(value);
            });

            $('.day-bulk').on('change', function() {
                const value = $(this).val();
                const day = $(this).data('day');
                if (!value) return;
                $(`.cell-select[data-day="${day}"]`).val(value);
            });
        });
    </script>
@endpush
