@extends('layouts.operation')

@section('title', 'RF Device Usage Report - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">RF Device Usage Report</h1>
                    <p class="wims-page-subtitle">Analyze RF utilization, assignment, and usage frequency.</p>
                    <p class="wims-breadcrumb">Administration / Reports / RF Device Usage Report</p>
                </div>
                <div class="flex gap-2">
                    <button id="exportRfUsage" type="button" class="wims-btn wims-btn-success">Export</button>
                    <a href="{{ route('administration.dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">RF Devices</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_devices'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">In Use</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['in_use_devices'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Sessions</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_sessions'] }}</p>
                </article>
            </div>

            <x-report-date-filter :action="route('administration.reports.rf-device-usage')" :filter="$filter" />

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-700">Periode Laporan</p>
                <p class="mt-1 text-sm text-slate-600">{{ $filter['label'] }}</p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table id="rfUsageTable" class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>RF Device</th>
                            <th>WMS Account</th>
                            <th>Status</th>
                            <th>Session Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row->code }}</td>
                                <td>{{ $row->wmsAccount?->username ?? '-' }}</td>
                                <td>{{ $row->status }}</td>
                                <td>{{ $row->working_sessions_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
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
            $('#exportRfUsage').on('click', function() {
                const rows = [];
                $('#rfUsageTable tr:visible').each(function() {
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
                link.download = 'rf-device-usage-report.csv';
                link.click();
            });
        });
    </script>
@endpush
