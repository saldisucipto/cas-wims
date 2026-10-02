@extends('layouts.operation')

@section('title', 'Create Shift Schedule - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Scheduling</p>
                    <h1 class="wims-page-title">Create Shifting Schedule</h1>
                    <p class="wims-page-subtitle">Division → pilih Core Employee → set initial shift → tentukan periode → generate.</p>
                </div>
                <a href="{{ route('administration.shift-schedules') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif
            @if (session('error'))
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</p>
            @endif

            <form action="{{ route('administration.shift-schedules.create') }}" method="GET" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <label class="mb-1 block text-sm font-semibold text-slate-700">Division</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <select name="division_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:max-w-sm">
                        <option value="">Pilih Division</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" @selected((int) $selectedDivisionId === (int) $division->id)>{{ $division->code }} - {{ $division->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Load Core Employee</button>
                </div>
            </form>

            <form action="{{ route('administration.shift-schedules.store') }}" method="POST" class="mt-6 space-y-4">
                @csrf

                <input type="hidden" name="division_id" value="{{ old('division_id', $selectedDivisionId) }}">

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Start Date</label>
                        <input type="date" name="period_start_date" value="{{ old('period_start_date', now()->toDateString()) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">End Date</label>
                        <input type="date" name="period_end_date" value="{{ old('period_end_date', now()->addDays(13)->toDateString()) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Manpower Planning (opsional)</label>
                        <select name="manpower_planning_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Tanpa Manpower Planning</option>
                            @foreach ($plannings as $planning)
                                <option value="{{ $planning->id }}" @selected((int) old('manpower_planning_id') === (int) $planning->id)>{{ $planning->planning_number }} ({{ $planning->planning_date }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-sm font-semibold text-slate-700">Core Employee by Division</p>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="select-all" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Select All</button>
                            <button type="button" id="clear-all" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Clear</button>
                            <button type="button" id="bulk-s1" class="rounded border border-blue-300 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50">Bulk Set Shift 1</button>
                            <button type="button" id="bulk-s2" class="rounded border border-violet-300 px-2 py-1 text-xs font-semibold text-violet-700 hover:bg-violet-50">Bulk Set Shift 2</button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="wims-table min-w-full text-left text-sm">
                            <thead>
                                <tr>
                                    <th class="text-center">Select</th>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th>Initial Shift</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($coreEmployees as $employee)
                                    @php
                                        $isChecked = in_array((string) $employee->id, (array) old('selected_employees', []), true);
                                        $initial = old('initial_shift_map.'.$employee->id, 'S1');
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="employee-check" name="selected_employees[]" value="{{ $employee->id }}" @checked($isChecked)>
                                        </td>
                                        <td>
                                            <div class="font-semibold text-slate-900">{{ $employee->employee_name }}</div>
                                            <div class="text-xs text-slate-500">{{ $employee->employee_code }}</div>
                                        </td>
                                        <td>{{ $employee->position?->name ?? '-' }}</td>
                                        <td>
                                            <select name="initial_shift_map[{{ $employee->id }}]" class="initial-shift rounded border border-slate-300 px-2 py-1 text-xs">
                                                <option value="S1" @selected($initial === 'S1')>Shift 1</option>
                                                <option value="S2" @selected($initial === 'S2')>Shift 2</option>
                                            </select>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <div class="wims-empty-state">Pilih Division dulu untuk menampilkan Core Employee.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="wims-btn wims-btn-primary">Generate Schedule</button>
            </form>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            const checks = $('.employee-check');
            const shifts = $('.initial-shift');

            $('#select-all').on('click', function() {
                checks.prop('checked', true);
            });

            $('#clear-all').on('click', function() {
                checks.prop('checked', false);
            });

            $('#bulk-s1').on('click', function() {
                shifts.val('S1');
            });

            $('#bulk-s2').on('click', function() {
                shifts.val('S2');
            });
        });
    </script>
@endpush
