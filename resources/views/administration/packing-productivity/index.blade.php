@extends('layouts.operation')

@section('title', 'Packing Productivity - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Packing Productivity</h1>
                    <p class="wims-page-subtitle">Produktivitas Picker dan Packer per operator dari transaksi Meson.</p>
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

            @php
                $blocks = $data['blocks'] ?? [];
                $role = (string) ($filters['role'] ?? '');
                $roleLabel = isset($blocks[$role]) ? $blocks[$role]['label'] : 'Semua role';
                $roleLink = function (string $value) use ($filters): string {
                    $params = array_filter(array_diff_key($filters, ['role' => true]));

                    if ($value !== '') {
                        $params['role'] = $value;
                    }

                    return route('administration.packing-productivity', $params);
                };
                $roleOptions = ['' => 'Semua', 'picker' => 'Picker', 'packer' => 'Packer'];
            @endphp

            <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                Picker dan Packer ditampilkan sebagai dua blok terpisah: Picker dihitung dari <code>Picking&Packing</code>, Packer dari <code>Packing</code>. Keduanya tidak pernah digabung.
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

            <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
                @foreach (['picker', 'packer'] as $blockKey)
                    @php
                        $block = $blocks[$blockKey] ?? null;
                        $isPicker = $blockKey === 'picker';
                    @endphp
                    @if ($block)
                        <article class="flex flex-col overflow-hidden rounded-xl border {{ $isPicker ? 'border-blue-200' : 'border-emerald-200' }} bg-white">
                            <div class="flex flex-wrap items-center justify-between gap-2 {{ $isPicker ? 'bg-blue-50' : 'bg-emerald-50' }} px-4 py-3">
                                <div>
                                    <p class="text-sm font-bold {{ $isPicker ? 'text-blue-700' : 'text-emerald-700' }}">{{ $block['label'] }} Productivity</p>
                                    <p class="text-xs text-slate-600">
                                        Transaksi: {{ implode(', ', $block['transaction_types'] ?? []) ?: '—' }}
                                        &middot; {{ number_format($block['summary']['total_workers']) }} worker
                                        &middot; {{ number_format($block['summary']['labor_hours'], 2) }} labour hours
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Orders / Hour</p>
                                    <p class="text-lg font-bold {{ $isPicker ? 'text-blue-800' : 'text-emerald-800' }}">{{ number_format($block['summary']['orders_per_hour'], 1) }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 border-b {{ $isPicker ? 'border-blue-100' : 'border-emerald-100' }} px-4 py-3 sm:grid-cols-3">
                                @foreach ([
                                    'Total Orders' => number_format($block['summary']['total_orders']),
                                    'SKU Lines' => number_format($block['summary']['total_lines']),
                                    'Total Items' => number_format($block['summary']['total_items']),
                                    'Workers' => number_format($block['summary']['total_workers']),
                                    'Orders / Hr' => number_format($block['summary']['orders_per_hour'], 1),
                                    'Items / Hr' => number_format($block['summary']['items_per_hour'], 1),
                                ] as $statLabel => $statValue)
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                        <p class="text-[11px] uppercase tracking-wide text-slate-500">{{ $statLabel }}</p>
                                        <p class="text-base font-bold text-slate-900">{{ $statValue }}</p>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex-1 overflow-x-auto">
                                <table class="wims-table min-w-full text-left text-sm">
                                    <thead>
                                        <tr>
                                            <th>Worker</th><th>Orders</th><th>SKU Lines</th><th>Items</th>
                                            <th>Orders/Hr</th><th>Items/Hr</th><th>Active Hrs</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($block['per_worker'] as $row)
                                            @php
                                                $workerLink = $row['daily_worker_id']
                                                    ? route('administration.packing-productivity', array_merge(array_filter($filters), ['daily_worker_id' => $row['daily_worker_id']]))
                                                    : route('administration.packing-productivity', array_merge(array_filter($filters), ['operator_id' => $row['operator_id']]));
                                            @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ $workerLink }}" class="font-semibold text-blue-700 hover:underline">{{ $row['name'] }}</a>
                                                    <div class="text-xs text-slate-500">{{ $row['username'] ?? '-' }} &middot; {{ $row['function'] }}</div>
                                                </td>
                                                <td>{{ number_format($row['orders']) }}</td>
                                                <td>{{ number_format($row['lines']) }}</td>
                                                <td>{{ number_format($row['items']) }}</td>
                                                <td>{{ $row['orders_per_hour'] !== null ? number_format($row['orders_per_hour'], 1) : '—' }}</td>
                                                <td>{{ $row['items_per_hour'] !== null ? number_format($row['items_per_hour'], 1) : '—' }}</td>
                                                <td>{{ number_format($row['estimated_active_hours'], 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7"><div class="wims-empty-state">Belum ada data {{ $block['label'] }} pada periode ini.</div></td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>

            @php $maxHourItems = collect($data['hourly'])->max('items') ?: 1; @endphp
            <div class="mt-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Hourly Productivity</h3>
                        <p class="text-sm text-slate-600">Jumlah item yang di-packing per jam &middot; {{ $roleLabel }}.</p>
                    </div>
                    <div class="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-0.5 text-xs font-semibold">
                        @foreach ($roleOptions as $value => $optionLabel)
                            <a href="{{ $roleLink($value) }}" class="rounded-md px-3 py-1.5 {{ $role === $value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">{{ $optionLabel }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="mt-2 rounded-xl border border-slate-200 bg-white p-4">
                    @php $hourlyCount = max(1, count($data['hourly'])); @endphp
                    <div class="grid min-h-45 items-end gap-2 overflow-x-auto pb-2"
                        style="grid-template-columns: repeat({{ $hourlyCount }}, minmax(52px, 1fr));">
                        @forelse ($data['hourly'] as $row)
                            <div class="flex flex-col items-center" title="{{ $row['date'] }} {{ $row['hour'] }} - {{ $row['items'] }} items">
                                <div class="text-[10px] font-semibold text-slate-700">{{ $row['items'] }}</div>
                                <div class="w-full rounded-t bg-blue-500" style="height: {{ max(4, round($row['items'] / $maxHourItems * 120)) }}px"></div>
                                <div class="mt-1 text-[10px] text-slate-500">{{ $row['hour'] }}</div>
                                <div class="text-[9px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m') }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada data hourly.</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-3 overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead><tr><th>Date</th><th>Hour</th><th>Orders</th><th>SKU Lines</th><th>Items</th></tr></thead>
                        <tbody>
                            @forelse ($data['hourly'] as $row)
                                <tr>
                                    <td>{{ $row['date'] }}</td>
                                    <td class="font-semibold">{{ $row['hour'] }}</td>
                                    <td>{{ number_format($row['orders']) }}</td>
                                    <td>{{ number_format($row['lines']) }}</td>
                                    <td>{{ number_format($row['items']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="wims-empty-state">Belum ada data hourly.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">Daily Productivity</h3>
                        <p class="text-sm text-slate-600">Produktivitas harian &middot; {{ $roleLabel }}.</p>
                    </div>
                    <div class="inline-flex rounded-lg border border-slate-300 bg-slate-50 p-0.5 text-xs font-semibold">
                        @foreach ($roleOptions as $value => $optionLabel)
                            <a href="{{ $roleLink($value) }}" class="rounded-md px-3 py-1.5 {{ $role === $value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">{{ $optionLabel }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="mt-2 overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead><tr><th>Date</th><th>Orders</th><th>SKU Lines</th><th>Items</th></tr></thead>
                        <tbody>
                            @forelse ($data['daily'] as $row)
                                <tr>
                                    <td class="font-semibold">{{ $row['date'] }}</td>
                                    <td>{{ number_format($row['orders']) }}</td>
                                    <td>{{ number_format($row['lines']) }}</td>
                                    <td>{{ number_format($row['items']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="wims-empty-state">Belum ada data harian.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
@endsection
