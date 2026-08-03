@extends('layouts.operation')

@section('title', 'Daily Worker Activity Report - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Daily Worker Activity Report</h1>
                    <p class="wims-page-subtitle">Review worker activity, workload, and last known session timeline.</p>
                    <p class="wims-breadcrumb">Administration / Reports / Daily Worker Activity Report</p>
                </div>
                <div class="flex gap-2">
                    <button id="exportWorkerActivity" type="button" class="wims-btn wims-btn-success">Export</button>
                    <a href="{{ route('administration.dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Workers</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_workers'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Active Workers</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['active_workers'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Sessions</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_sessions'] }}</p>
                </article>
            </div>

            <x-report-date-filter :action="route('administration.reports.daily-worker-activity')" :filter="$filter" />

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-700">Periode Laporan</p>
                <p class="mt-1 text-sm text-slate-600">{{ $filter['label'] }}</p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table id="workerActivityTable" class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Name</th>
                            <th>Function</th>
                            <th>Status</th>
                            <th>Session Count</th>
                            <th>Last Session</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->employee_code }}</td>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->function }}</td>
                                <td>{{ $row->status }}</td>
                                <td>{{ $row->working_sessions_count }}</td>
                                <td>{{ optional($row->workingSessions->first())->started_at?->format('d M Y H:i') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
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
            $('#exportWorkerActivity').on('click', function() {
                const rows = [];
                $('#workerActivityTable tr:visible').each(function() {
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
                link.download = 'daily-worker-activity-report.csv';
                link.click();
            });
        });
    </script>
@endpush
