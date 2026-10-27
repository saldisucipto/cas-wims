@extends('layouts.operation')

@section('title', 'Leaderboard Productivity - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Public Board</p>
                    <h1 class="wims-page-title">Leaderboard Productivity</h1>
                    <p class="wims-page-subtitle">
                        Peringkat produktivitas per pekerja &mdash;
                        {{ $date->translatedFormat('l, d F Y') }}
                    </p>
                    <p class="wims-breadcrumb">WIMS / Leaderboard Productivity</p>
                </div>

                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <form action="{{ route('leaderboard.productivity') }}" method="GET" class="flex items-center gap-2">
                        <input type="date" name="date" value="{{ $date->toDateString() }}"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Tampilkan</button>
                    </form>
                    <p class="text-xs text-slate-500">
                        Diperbarui {{ $generatedAt->format('d M Y H:i') }} &middot; auto refresh 60 detik
                    </p>
                </div>
            </div>

            @if ($isStale)
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <span class="font-semibold">Data Meson belum tersinkron untuk tanggal ini.</span>
                    Import terakhir: {{ $dataUntil ? $dataUntil->format('d M Y H:i') : 'belum pernah' }}.
                    Papan ini hanya menghitung transaksi sampai tanggal tersebut.
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
                @foreach ($boards as $board)
                    @php
                        $isPicker = $board['key'] === 'picker';
                        $accentText = $isPicker ? 'text-blue-700' : 'text-emerald-700';
                        $accentBar = $isPicker ? 'bg-blue-600' : 'bg-emerald-600';
                        $strip = $isPicker ? 'bg-blue-600' : 'bg-emerald-600';
                        $chip = $isPicker
                            ? 'border-blue-200 bg-blue-50 text-blue-700'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                        $maxOrders = max(1, (int) collect($board['rows'])->max('orders'));
                        $hasActive = (bool) ($board['active'] ?? false);
                    @endphp

                    <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-300/30">
                        <div class="h-1 w-full {{ $strip }}"></div>

                        <div class="flex items-start justify-between gap-3 px-5 pt-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">{{ $board['title'] }}</h2>
                                <p class="mt-1 inline-flex rounded-full border {{ $chip }} px-2 py-0.5 text-[11px] font-semibold">
                                    Transaction type: {{ implode(', ', $board['types']) }}
                                </p>
                            </div>
                            @if ($hasActive)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Live
                                </span>
                            @endif
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2 px-5">
                            <div class="rounded-lg border {{ $isPicker ? 'border-blue-200 bg-blue-50' : 'border-emerald-200 bg-emerald-50' }} px-3 py-2">
                                <p class="text-[11px] text-slate-500">Orders</p>
                                <p class="text-lg font-bold {{ $accentText }}">{{ number_format($board['totals']['orders']) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] text-slate-500">Items</p>
                                <p class="text-lg font-bold text-slate-900">{{ number_format($board['totals']['items']) }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-[11px] text-slate-500">Workers</p>
                                <p class="text-lg font-bold text-slate-900">{{ number_format($board['totals']['workers']) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 max-h-[30rem] divide-y divide-slate-100 overflow-y-auto border-t border-slate-100">
                            @forelse ($board['rows'] as $index => $row)
                                @php
                                    $rank = $index + 1;
                                    $pct = $row['orders'] > 0 ? max(3, round($row['orders'] / $maxOrders * 100)) : 0;
                                @endphp
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold
                                        {{ $rank === 1 ? 'bg-amber-100 text-amber-800' : ($rank === 2 ? 'bg-slate-200 text-slate-700' : ($rank === 3 ? 'bg-orange-100 text-orange-800' : 'bg-slate-100 text-slate-500')) }}">
                                        {{ $rank }}
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline justify-between gap-3">
                                            <p class="truncate text-sm font-semibold text-slate-900">{{ $row['name'] }}</p>
                                            <p class="shrink-0 text-sm font-bold tabular-nums text-slate-900">
                                                {{ number_format($row['orders']) }}
                                                <span class="text-[10px] font-medium text-slate-400">orders</span>
                                            </p>
                                        </div>
                                        <div class="mt-0.5 flex items-center justify-between gap-3 text-[11px] text-slate-500">
                                            <span class="truncate">
                                                {{ $row['function'] }}
                                                @if ($row['username'] && $row['username'] !== $row['name'])
                                                    &middot; {{ $row['username'] }}
                                                @endif
                                            </span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                {{ number_format($row['items']) }} items
                                                &middot;
                                                {{ $row['orders_per_hour'] !== null ? number_format($row['orders_per_hour'], 1).' order/jam' : '—' }}
                                                @if ($row['is_working'])
                                                    <span class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200">aktif</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full {{ $accentBar }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="px-5 py-10 text-center text-sm text-slate-500">
                                    Belum ada output {{ $board['title'] }} untuk tanggal ini.
                                </div>
                            @endforelse
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-2 text-[11px] text-slate-500">
                            <span>{{ number_format($board['totals']['lines']) }} SKU lines</span>
                            <span>{{ number_format($board['totals']['workers']) }} pekerja produktif</span>
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="mt-5 text-xs text-slate-500">
                Picking dihitung dari transaksi <code>Picking&Packing</code>, packing dari transaksi
                <code>Packing</code> &mdash; dua sumber terpisah, tidak pernah digabung.
                Window per sesi mulai dari clock-in dan berhenti saat sesi ditutup.
            </p>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            setTimeout(function() {
                window.location.reload();
            }, 60000);
        });
    </script>
@endpush
