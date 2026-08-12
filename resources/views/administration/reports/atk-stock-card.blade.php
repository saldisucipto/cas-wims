@extends('layouts.operation')

@section('title', 'Kartu Stok ATK - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Kartu Stok ATK</h1>
                    <p class="wims-page-subtitle">Pantau semua pergerakan stok ATK berdasarkan item, kategori, dan periode.
                    </p>
                    <p class="wims-breadcrumb">Administration / Reports / Kartu Stok ATK</p>
                </div>
                <div class="flex gap-2">
                    <button id="printAtkStockCard" type="button" class="wims-btn wims-btn-primary">Cetak Kartu Stok</button>
                    <button id="exportAtkStockCard" type="button" class="wims-btn wims-btn-success">Export</button>
                    <a href="{{ route('administration.dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Masuk</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_in'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Total Keluar</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_out'] }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Jenis ATK Bergerak</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $summary['total_items'] }}</p>
                </article>
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <form action="{{ route('administration.reports.atk-stock-card') }}" method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <select name="atk_item_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Nama ATK</option>
                            @foreach ($atkItems as $atkItem)
                                <option value="{{ $atkItem->id }}" @selected((string) ($filter['atk_item_id'] ?? '') === (string) $atkItem->id)>{{ $atkItem->name }}
                                </option>
                            @endforeach
                        </select>
                        <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category }}" @selected(($filter['category'] ?? '') === $category)>{{ $category }}
                                </option>
                            @endforeach
                        </select>
                        <select name="period" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" id="period">
                            <option value="today" @selected(($filter['period'] ?? '') === 'today')>Hari Ini</option>
                            <option value="this_month" @selected(($filter['period'] ?? 'this_month') === 'this_month')>Bulan Ini</option>
                            <option value="custom" @selected(($filter['period'] ?? '') === 'custom')>Custom Date Range</option>
                        </select>
                        <input name="start_date" id="start_date" type="date" value="{{ $filter['start_date'] ?? '' }}"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input name="end_date" id="end_date" type="date" value="{{ $filter['end_date'] ?? '' }}"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="wims-btn wims-btn-primary">Terapkan Filter</button>
                        <a href="{{ route('administration.reports.atk-stock-card') }}"
                            class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Reset
                            Filter</a>
                    </div>
                </form>
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-700">Periode Laporan</p>
                <p class="mt-1 text-sm text-slate-600">{{ $filter['label'] }}</p>
            </div>

            @if (($mode ?? 'detail') === 'summary')
                <div class="mt-6 overflow-x-auto">
                    <table id="atkStockCardTable" class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Kode ATK</th>
                                <th>Nama ATK</th>
                                <th>Kategori</th>
                                <th>Satuan</th>
                                <th>Total Masuk</th>
                                <th>Total Keluar</th>
                                <th>Saldo Akhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row['code'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['category'] }}</td>
                                    <td>{{ $row['unit'] }}</td>
                                    <td>{{ $row['total_in'] }}</td>
                                    <td>{{ $row['total_out'] }}</td>
                                    <td>{{ $row['balance'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="wims-empty-state">Tidak ada data untuk filter yang dipilih.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mt-6 overflow-x-auto">
                    <table id="atkStockCardTable" class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Nomor Transaksi</th>
                                <th>Jenis Transaksi</th>
                                <th>Reference</th>
                                <th>Item ATK</th>
                                <th>Masuk</th>
                                <th>Keluar</th>
                                <th>Saldo</th>
                                <th>User</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>{{ $row->transaction_at?->format('d M Y H:i') }}</td>
                                    <td>{{ $row->transaction_number ?: '-' }}</td>
                                    <td>{{ $row->transaction_type }}</td>
                                    <td>{{ $row->reference ?: ($row->supplier ?: '-') }}</td>
                                    <td>{{ $row->atkItem?->name ?? '-' }}</td>
                                    <td>{{ $row->quantity_in }}</td>
                                    <td>{{ $row->quantity_out }}</td>
                                    <td>{{ $row->balance }}</td>
                                    <td>{{ $row->taken_by_name ?: ($row->performer?->name ?? '-') }}</td>
                                    <td>{{ $row->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10">
                                        <div class="wims-empty-state">Tidak ada data untuk filter yang dipilih.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            function syncCustomMode() {
                const isCustom = $('#period').val() === 'custom';
                $('#start_date, #end_date').prop('disabled', !isCustom);

                if (!isCustom) {
                    $('#start_date, #end_date').removeAttr('required');
                    return;
                }

                $('#start_date, #end_date').attr('required', 'required');
            }

            $('#period').on('change', syncCustomMode);
            syncCustomMode();

            $('#printAtkStockCard').on('click', function() {
                const atkItemId = $('[name="atk_item_id"]').val();
                const period = $('#period').val();
                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();
                const category = $('[name="category"]').val();

                const params = new URLSearchParams();
                if (atkItemId) params.set('atk_item_id', atkItemId);
                if (period) params.set('period', period);
                if (startDate) params.set('start_date', startDate);
                if (endDate) params.set('end_date', endDate);
                if (category) params.set('category', category);

                const url = '{{ route('administration.reports.atk-stock-card.print') }}?' + params.toString();
                window.open(url, '_blank');
            });

            $('#exportAtkStockCard').on('click', function() {
                const rows = [];
                $('#atkStockCardTable tr:visible').each(function() {
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
                link.download = 'atk-stock-card.csv';
                link.click();
            });
        });
    </script>
@endpush
