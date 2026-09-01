@extends('layouts.operation')

@section('title', 'Packing Productivity - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Packing Productivity</h1>
                    <p class="wims-page-subtitle">Produktivitas packing per operator dari transaksi Meson.</p>
                    <p class="wims-breadcrumb">Administration / Reports / Packing Productivity</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('administration.packing-productivity.import') }}" class="wims-btn wims-btn-primary">Import / Refresh Data</a>
                    <a href="{{ route('administration.packing-productivity.history') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Import History</a>
                </div>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            @if ($lastBatch)
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <span class="font-semibold">Data Period:</span> {{ $lastBatch->start_date->format('d M Y') }} - {{ $lastBatch->end_date->format('d M Y') }}
                    &middot; <span class="font-semibold">Last Import:</span> {{ $lastBatch->created_at->format('d M Y H:i') }}
                    &middot; <span class="font-semibold">Imported By:</span> {{ $lastBatch->importer?->name ?? '-' }}
                    &middot; <span class="font-semibold">Source:</span> Meson Excel
                </div>
            @endif

            <div class="mt-5 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Orders</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($data['summary']['total_orders']) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total SKU Lines</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($data['summary']['total_lines']) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Items</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($data['summary']['total_items']) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Workers</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($data['summary']['total_workers']) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Avg Orders / Hour</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($data['summary']['orders_per_hour'], 1) }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Avg Items / Hour</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($data['summary']['items_per_hour'], 1) }}</p>
                </article>
            </div>

            <form action="{{ route('administration.packing-productivity') }}" method="GET" class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="warehouse_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse }}" @selected(($filters['warehouse_id'] ?? '') === $warehouse)>{{ $warehouse }}</option>
                    @endforeach
                </select>
                <select name="operator_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Operators</option>
                    @foreach ($operators as $operator)
                        <option value="{{ $operator->id }}" @selected((string) ($filters['operator_id'] ?? '') === (string) $operator->id)>{{ $operator->username }}</option>
                    @endforeach
                </select>
                <select name="daily_worker_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Workers</option>
                    @foreach ($dailyWorkers as $worker)
                        <option value="{{ $worker->id }}" @selected((string) ($filters['daily_worker_id'] ?? '') === (string) $worker->id)>{{ $worker->name }}</option>
                    @endforeach
                </select>
                <select name="function" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Functions</option>
                    @foreach ($functions as $function)
                        <option value="{{ $function }}" @selected(($filters['function'] ?? '') === $function)>{{ $function }}</option>
                    @endforeach
                </select>
                <select name="transaction_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Transaction Types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(($filters['transaction_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Apply Filters</button>
            </form>

            @if (! empty($filters['daily_worker_id']) || ! empty($filters['operator_id']))
                @php
                    $selectedWorker = ! empty($filters['daily_worker_id']) ? $dailyWorkers->firstWhere('id', (int) $filters['daily_worker_id']) : null;
                    $selectedOperator = ! empty($filters['operator_id']) ? $operators->firstWhere('id', (int) $filters['operator_id']) : null;
                    $filterLabel = $selectedWorker?->name ?? $selectedOperator?->username ?? '-';
                    $clearFilterLink = route('administration.packing-productivity', array_filter(array_diff_key($filters, ['daily_worker_id' => true, 'operator_id' => true])));
                @endphp
                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Menampilkan: <span class="font-semibold">{{ $filterLabel }}</span>
                    <a href="{{ $clearFilterLink }}" class="ml-2 font-semibold underline">Clear filter</a>
                </div>
            @endif

            <div class="mt-6">
                <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Productivity per Worker</h3>
                <div class="mt-2 overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Worker</th><th>Function</th><th>Orders</th><th>SKU Lines</th><th>Items</th>
                                <th>Orders/Hr</th><th>Items/Hr</th><th>Est. Active Hrs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data['per_worker'] as $row)
                                @php
                                    $workerLink = $row['daily_worker_id']
                                        ? route('administration.packing-productivity', array_merge(array_filter($filters), ['daily_worker_id' => $row['daily_worker_id']]))
                                        : route('administration.packing-productivity', array_merge(array_filter($filters), ['operator_id' => $row['operator_id']]));
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ $workerLink }}" class="font-semibold text-blue-700 hover:underline">{{ $row['name'] }}</a>
                                        @if ($row['username'] && $row['name'] !== $row['username'])
                                            <div class="text-xs text-slate-500">{{ $row['username'] }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $row['function'] }}</td>
                                    <td>{{ number_format($row['orders']) }}</td>
                                    <td>{{ number_format($row['lines']) }}</td>
                                    <td>{{ number_format($row['items']) }}</td>
                                    <td>{{ number_format($row['orders_per_hour'], 1) }}</td>
                                    <td>{{ number_format($row['items_per_hour'], 1) }}</td>
                                    <td>{{ number_format($row['estimated_active_hours'], 1) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="wims-empty-state">Belum ada data produktivitas. Silakan import data Meson.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @php $maxHourItems = collect($data['hourly'])->max('items') ?: 1; @endphp
            <div class="mt-6">
                <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Hourly Productivity</h3>
                <p class="text-sm text-slate-600">Jumlah item yang di-packing per jam.</p>
                <div class="mt-2 rounded-xl border border-slate-200 bg-white p-4">
                    @php $hourlyCount = max(1, count($data['hourly'])); @endphp
                    <div class="grid min-h-45 items-end gap-2 overflow-x-auto pb-2"
                        style="grid-template-columns: repeat({{ $hourlyCount }}, minmax(52px, 1fr));">
                        @forelse ($data['hourly'] as $row)
                            <div class="flex flex-col items-center" title="{{ $row['hour'] }} - {{ $row['items'] }} items">
                                <div class="text-[10px] font-semibold text-slate-700">{{ $row['items'] }}</div>
                                <div class="w-full rounded-t bg-blue-500" style="height: {{ max(4, round($row['items'] / $maxHourItems * 120)) }}px"></div>
                                <div class="mt-1 text-[10px] text-slate-500">{{ $row['hour'] }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada data hourly.</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead><tr><th>Hour</th><th>Orders</th><th>SKU Lines</th><th>Items</th></tr></thead>
                        <tbody>
                            @forelse ($data['hourly'] as $row)
                                <tr>
                                    <td class="font-semibold">{{ $row['hour'] }}</td>
                                    <td>{{ number_format($row['orders']) }}</td>
                                    <td>{{ number_format($row['lines']) }}</td>
                                    <td>{{ number_format($row['items']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="wims-empty-state">Belum ada data hourly.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
@endsection
