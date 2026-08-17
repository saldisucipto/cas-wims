@extends('layouts.operation')

@section('title', 'Shift Schedules - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Scheduling</p>
                    <h1 class="wims-page-title">Monthly Shift Schedules</h1>
                    <p class="wims-page-subtitle">Jadwal shift bulanan Core Employee berdasarkan rotasi mingguan.</p>
                    <p class="wims-breadcrumb">Administration / Scheduling / Shift Schedules</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('administration.shift-schedules.create') }}" class="wims-btn wims-btn-primary">New Schedule</a>
                    <a href="{{ route('administration.dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            <form action="{{ route('administration.shift-schedules') }}" method="GET" class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search schedule number..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="wims-table min-w-full text-left text-sm">
                    <thead><tr><th>Schedule No</th><th>Period</th><th>Status</th><th>Created By</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $row->schedule_number }}</td>
                                <td>{{ \Carbon\Carbon::create($row->year, $row->month, 1)->translatedFormat('F Y') }}</td>
                                <td>
                                    @if ($row->status === 'FINAL')
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">FINAL</span>
                                    @elseif ($row->status === 'CANCELLED')
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">CANCELLED</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">DRAFT</span>
                                    @endif
                                </td>
                                <td>{{ $row->creator?->name ?? '-' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="{{ route('administration.shift-schedules.show', $row) }}" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">View</a>
                                        <a href="{{ route('administration.shift-schedules.print', $row) }}" target="_blank" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Print</a>
                                        <form action="{{ route('administration.shift-schedules.duplicate', $row) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Duplicate</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="wims-empty-state">Belum ada shift schedule.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
    </main>
@endsection
