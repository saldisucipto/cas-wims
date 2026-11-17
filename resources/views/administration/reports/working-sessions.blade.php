@extends('layouts.operation')

@section('title', 'Working Session Report - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Working Session Report</h1>
                    <p class="wims-page-subtitle">Ringkasan sesi kerja manpower: pemakaian RF dan pemakaian Packing / Station.
                    </p>
                    <p class="wims-breadcrumb">Administration / Reports / Working Session Report</p>
                </div>
                <div class="flex gap-2">
                    @if ($canForceClose)
                        <form method="POST" action="{{ route('administration.reports.working-sessions.force-close-all') }}"
                            class="force-close-all-form inline-block">
                            @csrf
                            <button type="submit"
                                class="wims-btn inline-flex items-center justify-center border-red-600 bg-red-600 text-white hover:bg-red-700">
                                Force Close All
                            </button>
                        </form>
                    @endif
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

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <article class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-blue-700">MP pakai RF</p>
                            <p class="text-xs text-blue-600">Sesi tipe RF / picking.</p>
                        </div>
                        <span
                            class="inline-flex rounded-full border border-blue-300 bg-white px-2 py-0.5 text-xs font-semibold text-blue-700">RF</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div>
                            <p class="text-xs text-blue-600">MP</p>
                            <p class="mt-0.5 text-xl font-bold text-blue-800">{{ $summary['rf']['total_workers'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-blue-600">Sesi</p>
                            <p class="mt-0.5 text-xl font-bold text-blue-800">{{ $summary['rf']['total_sessions'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-blue-600">Jam</p>
                            <p class="mt-0.5 text-xl font-bold text-blue-800">{{ $summary['rf']['total_hours'] }}</p>
                        </div>
                    </div>
                    <a href="{{ route('administration.reports.working-sessions', array_filter([
                        'period' => $filter['period'] ?? 'this_month',
                        'start_date' => $filter['start_date'] ?? null,
                        'end_date' => $filter['end_date'] ?? null,
                        'worker_name' => $workerName ?? null,
                        'type' => 'rf',
                    ])) }}"
                        class="mt-3 inline-flex items-center text-xs font-semibold text-blue-700 hover:text-blue-900 hover:underline">Lihat
                        detail RF</a>
                </article>
                <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-emerald-700">MP pakai Packing / Station</p>
                            <p class="text-xs text-emerald-600">Sesi tipe packing.</p>
                        </div>
                        <span
                            class="inline-flex rounded-full border border-emerald-300 bg-white px-2 py-0.5 text-xs font-semibold text-emerald-700">Packing</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div>
                            <p class="text-xs text-emerald-600">MP</p>
                            <p class="mt-0.5 text-xl font-bold text-emerald-800">{{ $summary['packing']['total_workers'] }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-emerald-600">Sesi</p>
                            <p class="mt-0.5 text-xl font-bold text-emerald-800">
                                {{ $summary['packing']['total_sessions'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-emerald-600">Jam</p>
                            <p class="mt-0.5 text-xl font-bold text-emerald-800">{{ $summary['packing']['total_hours'] }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('administration.reports.working-sessions', array_filter([
                        'period' => $filter['period'] ?? 'this_month',
                        'start_date' => $filter['start_date'] ?? null,
                        'end_date' => $filter['end_date'] ?? null,
                        'worker_name' => $workerName ?? null,
                        'type' => 'packing',
                    ])) }}"
                        class="mt-3 inline-flex items-center text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">Lihat
                        detail Packing</a>
                </article>
            </div>

            <x-report-date-filter :action="route('administration.reports.working-sessions')" :filter="$filter" />

            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <form action="{{ route('administration.reports.working-sessions') }}" method="GET"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end">
                    <input type="hidden" name="period" value="{{ $filter['period'] ?? 'today' }}">
                    <input type="hidden" name="start_date" value="{{ $filter['start_date'] ?? '' }}">
                    <input type="hidden" name="end_date" value="{{ $filter['end_date'] ?? '' }}">
                    <div>
                        <label for="worker_name" class="mb-1 block text-sm font-semibold text-slate-700">Cari Nama Worker</label>
                        <input id="worker_name" name="worker_name" type="text" value="{{ $workerName ?? '' }}"
                            placeholder="Contoh: Budi"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="type" class="mb-1 block text-sm font-semibold text-slate-700">Tipe Sesi</label>
                        <select id="type" name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($type ?? 'all') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="wims-btn wims-btn-primary">Cari</button>
                        <a href="{{ route('administration.reports.working-sessions', ['period' => $filter['period'] ?? 'today', 'start_date' => $filter['start_date'] ?? null, 'end_date' => $filter['end_date'] ?? null]) }}"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset</a>
                    </div>
                </form>
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-700">Periode Laporan</p>
                <p class="mt-1 text-sm text-slate-600">{{ $filter['label'] }}</p>
            </div>

            <div class="mt-6 overflow-x-auto">
                <h2 class="mb-2 text-base font-bold uppercase tracking-wide text-slate-900">Rekap Pemakaian MP per Tipe</h2>
                <table class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Tipe Sesi</th>
                            <th>MP Terpakai</th>
                            <th>Total Sesi</th>
                            <th>Total Jam Kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <span
                                    class="inline-flex rounded-full border border-blue-300 bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">RF</span>
                            </td>
                            <td>{{ $summary['rf']['total_workers'] }}</td>
                            <td>{{ $summary['rf']['total_sessions'] }}</td>
                            <td>{{ $summary['rf']['total_hours'] }}</td>
                        </tr>
                        <tr>
                            <td>
                                <span
                                    class="inline-flex rounded-full border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Packing / Station</span>
                            </td>
                            <td>{{ $summary['packing']['total_workers'] }}</td>
                            <td>{{ $summary['packing']['total_sessions'] }}</td>
                            <td>{{ $summary['packing']['total_hours'] }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-300 font-semibold">
                            <td>Total</td>
                            <td>{{ $summary['total_workers'] }}</td>
                            <td>{{ $summary['total_sessions'] }}</td>
                            <td>{{ $summary['total_hours'] }}</td>
                        </tr>
                    </tfoot>
                </table>
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
                                <td>{{ $row->started_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }}</td>
                                <td>{{ $row->dailyWorker?->name ?? '-' }}</td>
                                <td>
                                    @if ($row->session_type === 'rf')
                                        <span
                                            class="inline-flex rounded-full border border-blue-300 bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">RF</span>
                                    @elseif ($row->session_type === 'packing')
                                        <span
                                            class="inline-flex rounded-full border border-emerald-300 bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Packing</span>
                                    @else
                                        <span
                                            class="inline-flex rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ ucfirst((string) $row->session_type) }}</span>
                                    @endif
                                </td>
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
                                    <div class="flex flex-col items-start gap-1.5">
                                        @php
                                            $detailParams = [
                                                'start_date' => $row->started_at?->toDateString(),
                                                'end_date' => $row->started_at?->toDateString(),
                                            ];
                                            if ($row->daily_worker_id) {
                                                $detailParams['daily_worker_id'] = $row->daily_worker_id;
                                            } elseif ($row->wms_account_id) {
                                                $detailParams['operator_id'] = $row->wms_account_id;
                                            }
                                        @endphp
                                        <a href="{{ route('administration.packing-productivity', array_filter($detailParams)) }}"
                                            class="inline-flex items-center justify-center rounded-lg border border-blue-600 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">Detail</a>
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
                                        @endif
                                    </div>
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

            $('.force-close-all-form').on('submit', function(event) {
                event.preventDefault();

                const form = this;

                Swal.fire({
                    title: 'Force Close All Working Sessions',
                    text: 'This action will immediately terminate all active working sessions and release all assigned resources. This action should only be performed in emergency situations.',
                    icon: 'warning',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                    confirmButtonText: 'Force Close All',
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

            @if (session('success_force_close_all'))
                Swal.fire({
                    title: 'All Working Sessions Closed',
                    text: '{{ session('success_force_close_all') }} active working sessions have been forcefully closed. All assigned resources are now available for use.',
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
