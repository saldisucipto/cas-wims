@extends('layouts.operation')

@section('title', 'Manpower Report - WIMS')

@php
    $formatNumber = fn($value) => ($value == (int) $value) ? number_format((int) $value) : number_format($value, 2);
    $queryBase = array_filter([
        'period' => $filter['period'],
        'start_date' => $filter['start_date'],
        'end_date' => $filter['end_date'],
        'role' => $role,
        'q' => $search,
    ]);
@endphp

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Manpower Report</h1>
                    <p class="wims-page-subtitle">
                        Durasi kerja dan produktivitas per manpower per tanggal.
                    </p>
                    <p class="wims-breadcrumb">Administration / Reports / Manpower Report</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('administration.reports.manpower.print', $queryBase) }}" target="_blank"
                        class="wims-btn wims-btn-success">Export PDF</a>
                    <a href="{{ route('administration.reports.working-sessions', $queryBase) }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Working
                        Session</a>
                    <a href="{{ route('administration.dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            <x-report-date-filter :action="route('administration.reports.manpower')" :filter="$filter" />

            <form action="{{ route('administration.reports.manpower') }}" method="GET"
                class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <input type="hidden" name="period" value="{{ $filter['period'] }}">
                <input type="hidden" name="start_date" value="{{ $filter['start_date'] }}">
                <input type="hidden" name="end_date" value="{{ $filter['end_date'] }}">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end">
                    <div>
                        <label for="q" class="mb-1 block text-sm font-semibold text-slate-700">Cari Manpower</label>
                        <input id="q" name="q" type="text" value="{{ $search }}" placeholder="Nama / kode / akun WMS"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="role" class="mb-1 block text-sm font-semibold text-slate-700">Role</label>
                        <select id="role" name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="wims-btn wims-btn-primary">Terapkan</button>
                        <a href="{{ route('administration.reports.manpower') }}"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset</a>
                    </div>
                </div>
            </form>

            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <span class="font-semibold text-slate-700">Periode:</span> {{ $filter['label'] }}
                @if ($search !== '')
                    &middot; <span class="font-semibold text-slate-700">Pencarian:</span> {{ $search }}
                @endif
            </div>

            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Manpower</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $formatNumber($summary['total_workers']) }}</p>
                    <p class="text-xs text-slate-500">{{ $formatNumber($summary['total_days']) }} tanggal</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Durasi Kerja</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $formatNumber($summary['duration_hours']) }} <span class="text-sm font-semibold text-slate-500">jam</span></p>
                    <p class="text-xs text-slate-500">rata-rata {{ $formatNumber($summary['avg_duration_hours']) }} jam / hari-kerja</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Jam Aktif</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $formatNumber($summary['active_hours']) }} <span class="text-sm font-semibold text-slate-500">jam</span></p>
                    <p class="text-xs text-slate-500">{{ $formatNumber($summary['total_sessions']) }} sesi kerja</p>
                </article>
                <article class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-blue-700">Total Orders / Jam</p>
                    <p class="mt-1 text-2xl font-bold text-blue-800">{{ $summary['orders_per_hour'] !== null ? $formatNumber($summary['orders_per_hour']) : '—' }}</p>
                    <p class="text-xs text-blue-700">{{ $formatNumber($summary['total_orders']) }} orders · {{ $formatNumber($summary['total_items']) }} items</p>
                </article>
            </div>

            <div class="mt-6">
                <h2 class="text-base font-bold uppercase tracking-wide text-slate-900">Ringkasan per Manpower</h2>
                <p class="text-sm text-slate-600">Akumulasi seluruh tanggal dalam periode.</p>
                <div class="mt-2 overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Manpower</th>
                                <th>Function</th>
                                <th>Hari</th>
                                <th>Sesi</th>
                                <th>Durasi Kerja</th>
                                <th>Jam Aktif</th>
                                <th>Orders</th>
                                <th>Items</th>
                                <th>Order/Jam</th>
                                <th>Order/Hari</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($perWorker as $worker)
                                <tr>
                                    <td>
                                        <span class="font-semibold text-slate-900">{{ $worker['name'] }}</span>
                                        <div class="text-[11px] text-slate-500">{{ $worker['employee_code'] ?? '-' }}</div>
                                    </td>
                                    <td>{{ $worker['function'] }}</td>
                                    <td>{{ $worker['days'] }}</td>
                                    <td>{{ $worker['sessions'] }}</td>
                                    <td>{{ $formatNumber($worker['duration_hours']) }} jam</td>
                                    <td>{{ $formatNumber($worker['active_hours']) }} jam</td>
                                    <td class="font-semibold">{{ $formatNumber($worker['orders']) }}</td>
                                    <td>{{ $formatNumber($worker['items']) }}</td>
                                    <td>{{ $worker['orders_per_hour'] !== null ? $formatNumber($worker['orders_per_hour']) : '—' }}</td>
                                    <td>{{ $worker['orders_per_day'] !== null ? $formatNumber($worker['orders_per_day']) : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="wims-empty-state">Belum ada data manpower pada periode ini.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @php
                $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
                $collection = collect($rows);
                $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                    $collection->forPage($page, $perPage)->values(),
                    $collection->count(),
                    $perPage,
                    $page,
                    ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath(), 'query' => $queryBase],
                );
            @endphp

            <div class="mt-6">
                <h2 class="text-base font-bold uppercase tracking-wide text-slate-900">Detail per Manpower per Tanggal</h2>
                <p class="text-sm text-slate-600">
                    Durasi kerja = clock-in sampai clock-out. Jam aktif = durasi dikurangi jeda diam lebih dari 30 menit.
                    Sesi yang tidak ditutup pada hari yang sama ditandai <span class="font-semibold">lintas hari</span>.
                </p>
                <div class="mt-2 overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Manpower</th>
                                <th>Role</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Durasi</th>
                                <th>Jam Aktif</th>
                                <th>Orders</th>
                                <th>Items</th>
                                <th>Order/Jam</th>
                                <th>Station / RF</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paginated as $row)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold">{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                    <td>
                                        <span class="font-semibold text-slate-900">{{ $row['name'] }}</span>
                                        <div class="text-[11px] text-slate-500">
                                            {{ $row['employee_code'] ?? '-' }}
                                            @if ($row['username'])
                                                &middot; {{ $row['username'] }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="capitalize">{{ $row['role'] }}</td>
                                    <td>{{ $row['clock_in']?->format('H:i') ?? '-' }}</td>
                                    <td>{{ $row['clock_out']?->format('H:i') ?? '-' }}</td>
                                    <td>{{ $formatNumber($row['duration_hours']) }} jam</td>
                                    <td>{{ $formatNumber($row['active_hours']) }} jam</td>
                                    <td class="font-semibold">{{ $formatNumber($row['orders']) }}</td>
                                    <td>{{ $formatNumber($row['items']) }}</td>
                                    <td>{{ $row['orders_per_hour'] !== null ? $formatNumber($row['orders_per_hour']) : '—' }}</td>
                                    <td class="text-xs text-slate-600">{{ $row['station'] ?: ($row['device'] ?: '-') }}</td>
                                    <td>
                                        @if ($row['is_working'])
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Working</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Selesai</span>
                                        @endif
                                        @if (! empty($row['cross_day']))
                                            <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800" title="Sesi tidak ditutup pada hari yang sama">
                                                lintas hari
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12">
                                        <div class="wims-empty-state">Belum ada data untuk periode ini.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $paginated->links() }}</div>
            </div>
        </section>
    </main>
@endsection
