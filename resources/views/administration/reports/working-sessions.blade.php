@extends('layouts.operation')

@section('title', 'Working Session Report - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Working Session Report</h1>
                    <p class="wims-page-subtitle">Track all active and completed working sessions by worker and device usage.
                    </p>
                    <p class="wims-breadcrumb">Administration / Reports / Working Session Report</p>
                </div>
                <div class="flex gap-2">
                    <button id="exportWorkingSessions" type="button" class="wims-btn wims-btn-success">Export</button>
                    <a href="{{ $backRoute }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Rows</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_sessions'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Pekerja</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_workers'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Jam Kerja</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_hours'] }}</p>
                </article>
            </div>

            <x-report-date-filter :action="route('administration.reports.working-sessions')" :filter="$filter" />

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-700">Periode Laporan</p>
                <p class="mt-1 text-sm text-slate-600">{{ $filter['label'] }}</p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table id="workingSessionsTable" class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Start</th>
                            <th>Worker</th>
                            <th>Type</th>
                            <th>Station</th>
                            <th>RF</th>
                            <th>WMS Account</th>
                            <th>Status</th>
                            <th>Close Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->started_at?->format('d M Y H:i') }}</td>
                                <td>{{ $row->dailyWorker?->name ?? '-' }}</td>
                                <td>{{ ucfirst($row->session_type) }}</td>
                                <td>{{ $row->packingStation?->name ?? '-' }}</td>
                                <td>{{ $row->rfDevice?->code ?? '-' }}</td>
                                <td>{{ $row->wmsAccount?->username ?? '-' }}</td>
                                <td>{{ $row->status }}</td>
                                <td>
                                    @if ($row->close_type === 'Force Close')
                                        <span
                                            class="inline-flex rounded-full border border-red-300 bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Force
                                            Close</span>
                                    @else
                                        <span
                                            class="inline-flex rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Normal</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($canForceClose && $row->status === 'Working')
                                        <form method="POST"
                                            action="{{ route('administration.reports.working-sessions.force-close', $row) }}"
                                            class="force-close-form inline-block"
                                            data-worker="{{ $row->dailyWorker?->name ?? '-' }}"
                                            data-resource="{{ $row->rfDevice?->code ?? ($row->packingStation?->name ?? '-') }}">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center gap-2 rounded-lg border border-red-600 bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                                <span aria-hidden="true">⏻</span>
                                                <span>Force Close</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="wims-empty-state">Tidak ada data untuk periode yang dipilih.</div>
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

@push('scripts')
    <script>
        $(function() {
            $('.force-close-form').on('submit', function(event) {
                event.preventDefault();

                const form = this;

                Swal.fire({
                    title: 'Force Close Working Session',
                    text: 'This action will immediately terminate the current working session and release all assigned resources. This action should only be performed in emergency situations.',
                    icon: 'warning',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    confirmButtonText: 'Force Close',
                    confirmButtonColor: '#dc2626'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            @if (session('success_force_close'))
                Swal.fire({
                    title: 'Working Session Closed',
                    text: 'The Working Session has been forcefully closed. All assigned resources have been released and are now available for use.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                });
            @endif

            $('#exportWorkingSessions').on('click', function() {
                const rows = [];
                $('#workingSessionsTable tr:visible').each(function() {
                    const cols = [];
                    $(this).find('th,td').each(function() {
                        cols.push('"' + $(this).text().trim().replace(/"/g, '""') + '"');
                    });
                    rows.push(cols.join(','));
                });

                const blob = new Blob([rows.join('\n')], {
                    type: 'text/csv;charset=utf-8;'
                });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'working-session-report.csv';
                link.click();
            });
        });
    </script>
@endpush
