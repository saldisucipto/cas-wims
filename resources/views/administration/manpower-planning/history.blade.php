@extends('layouts.operation')

@section('title', 'Manpower Planning History - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Manpower Planning History</h1>
                    <p class="wims-page-subtitle">Riwayat planning yang tersimpan sebagai transaction/document.</p>
                    <p class="wims-breadcrumb">Administration / Reports / Manpower Planning History</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('administration.manpower-planning') }}" class="wims-btn wims-btn-primary">New Planning</a>
                    <a href="{{ route('administration.dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            <form action="{{ route('administration.manpower-planning.history') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search number/notes..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Status</option>
                    @foreach (['DRAFT', 'CALCULATED', 'FINAL', 'CANCELLED'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Filter</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Planning No</th>
                            <th>Date</th>
                            <th>Inbound</th>
                            <th>Outbound</th>
                            <th>Total MPP</th>
                            <th>Recommendation</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $row->planning_number }}</td>
                                <td>{{ \Carbon\Carbon::parse($row->planning_date)->translatedFormat('d M Y') }}</td>
                                <td>{{ number_format($row->inbound_volume) }}</td>
                                <td>{{ number_format($row->outbound_volume) }}</td>
                                <td>{{ number_format($row->total_mpp) }}</td>
                                <td>{{ $row->recommendation }}</td>
                                <td>
                                    @if ($row->status === 'FINAL')
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">FINAL</span>
                                    @elseif ($row->status === 'CALCULATED')
                                        <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">CALCULATED</span>
                                    @elseif ($row->status === 'CANCELLED')
                                        <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">CANCELLED</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">DRAFT</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <a href="{{ route('administration.manpower-planning.show', $row) }}"
                                            class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">View</a>
                                        @if (! in_array($row->status, ['FINAL', 'CANCELLED'], true))
                                            <a href="{{ route('administration.manpower-planning.edit', $row) }}"
                                                class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Edit</a>
                                        @endif
                                        <a href="{{ route('administration.manpower-planning.print', $row) }}" target="_blank"
                                            class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Print</a>
                                        <form action="{{ route('administration.manpower-planning.duplicate', $row) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Duplicate</button>
                                        </form>
                                        @if (in_array($row->status, ['DRAFT', 'CALCULATED'], true))
                                            <form action="{{ route('administration.manpower-planning.finalize', $row) }}" method="POST"
                                                onsubmit="return confirm('Finalize this planning?');">
                                                @csrf
                                                <button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Finalize</button>
                                            </form>
                                        @endif
                                        @if ($row->status !== 'CANCELLED')
                                            <form action="{{ route('administration.manpower-planning.cancel', $row) }}" method="POST"
                                                onsubmit="return confirm('Cancel this planning?');">
                                                @csrf
                                                <button type="submit" class="rounded border border-red-300 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">Cancel</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="wims-empty-state">Belum ada planning tersimpan.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
    </main>
@endsection
