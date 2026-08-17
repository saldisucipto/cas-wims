@extends('layouts.operation')

@section('title', 'Handover - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Shift Schedule</p>
                    <h1 class="wims-page-title">Handover / Outstanding Jobs — {{ $schedule->schedule_number }}</h1>
                    <p class="wims-page-subtitle">{{ \Carbon\Carbon::create($schedule->year, $schedule->month, 1)->translatedFormat('F Y') }} · Transfer unfinished work across shifts with accountability.</p>
                </div>
                <a href="{{ route('administration.shift-schedules.show', $schedule) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">Record Handover / Outstanding Job</h2>
                <form action="{{ route('administration.shift-schedules.handover.store', $schedule) }}" method="POST" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Date</label>
                        <input type="date" name="handover_date" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">From Shift</label>
                        <select name="shift_from" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($shiftCodes as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">To Shift</label>
                        <select name="shift_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($shiftCodes as $code)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Job Type</label>
                        <select name="job_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach (\App\Models\ShiftHandover::JOB_TYPES as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Quantity (opsional)</label>
                        <input type="number" step="0.01" name="quantity" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Unit</label>
                        <input type="text" name="unit" placeholder="pcs / box / order" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-3">
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Description</label>
                        <textarea name="description" rows="2" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. 3 outstanding BCO orders pending QC"></textarea>
                    </div>
                    <div class="md:col-span-3">
                        <button type="submit" class="wims-btn wims-btn-primary">Record Handover</button>
                    </div>
                </form>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr><th>Date</th><th>From</th><th>To</th><th>Job Type</th><th>Description</th><th>Qty</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($handovers as $handover)
                            <tr>
                                <td>{{ $handover->handover_date->format('d M Y') }}</td>
                                <td>{{ $handover->shift_from }}</td>
                                <td>{{ $handover->shift_to }}</td>
                                <td>{{ $handover->job_type }}</td>
                                <td class="max-w-xs">{{ $handover->description }}</td>
                                <td>{{ $handover->quantity ? $handover->quantity.' '.($handover->unit ?? '') : '-' }}</td>
                                <td>
                                    @if ($handover->status === 'OPEN')
                                        <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">OPEN</span>
                                    @elseif ($handover->status === 'TRANSFERRED')
                                        <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">TRANSFERRED</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">CLOSED</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @if ($handover->status === 'OPEN')
                                            <form action="{{ route('administration.shift-schedules.handover.transfer', [$schedule, $handover]) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Transfer</button>
                                            </form>
                                        @endif
                                        @if ($handover->status !== 'CLOSED')
                                            <form action="{{ route('administration.shift-schedules.handover.close', [$schedule, $handover]) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Close</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><div class="wims-empty-state">Belum ada handover / outstanding job.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
