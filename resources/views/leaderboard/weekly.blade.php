@extends('layouts.operation')

@section('title', 'Weekly Productivity Rewards - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Rewards Board</p>
                    <h1 class="wims-page-title">Weekly Productivity Rewards</h1>
                    <p class="wims-page-subtitle">
                        Akumulasi productivity minggu {{ $weekStart->translatedFormat('d M Y') }} - {{ $weekEnd->translatedFormat('d M Y') }}
                    </p>
                    <p class="wims-breadcrumb">WIMS / Weekly Leaderboard</p>
                </div>

                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <form action="{{ route('leaderboard.productivity.weekly') }}" method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="date" name="date" value="{{ $date->toDateString() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Tampilkan</button>
                    </form>
                    <a href="{{ route('leaderboard.productivity.weekly.print', ['date' => $date->toDateString()]) }}" target="_blank" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white">
                        Cetak Gabungan / PDF
                    </a>
                    <p class="text-xs text-slate-500">Diperbarui {{ $generatedAt->format('d M Y H:i') }} &middot; Minggu berjalan {{ \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY)->translatedFormat('d M') }} - {{ \Carbon\Carbon::now()->endOfWeek(\Carbon\Carbon::SUNDAY)->translatedFormat('d M Y') }}</p>
                </div>
            </div>

            @if (collect($boards)->contains(fn (array $board): bool => $board['is_stale']))
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Data transaksi belum mencakup seluruh akhir minggu pada salah satu role. Pastikan import Meson sudah selesai sebelum rewards diproses.
                </div>
            @endif

            <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
                @foreach ($boards as $board)
                    @php
                        $isPicker = $board['key'] === 'picker';
                        $accentText = $isPicker ? 'text-blue-700' : 'text-emerald-700';
                        $accent = $isPicker ? 'blue' : 'emerald';
                    @endphp
                    <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="h-1 w-full {{ $isPicker ? 'bg-blue-600' : 'bg-emerald-600' }}"></div>
                        <div class="flex items-start justify-between gap-3 px-5 pt-4">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">{{ $board['title'] }}</h2>
                                <p class="mt-1 text-xs text-slate-500">{{ implode(', ', $board['types']) }}</p>
                            </div>
                            <a href="{{ route('leaderboard.productivity.weekly.print', ['date' => $date->toDateString(), 'role' => $board['role']]) }}" target="_blank" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700">Cetak Role</a>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-2 px-5">
                            <div class="rounded-lg border border-{{ $accent }}-200 bg-{{ $accent }}-50 px-3 py-2">
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

                        <div class="mt-4 border-t border-slate-100 px-5 pt-3">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Top 3 Rewards</p>
                            @forelse ($board['top_three'] as $row)
                                <div class="mb-2 flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $row['rank'] === 1 ? 'bg-amber-100 text-amber-800' : ($row['rank'] === 2 ? 'bg-slate-200 text-slate-700' : 'bg-orange-100 text-orange-800') }} text-xs font-bold">{{ $row['rank'] }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $row['name'] }}</p>
                                        <p class="text-xs text-slate-500">{{ $row['function'] }} &middot; {{ $row['days_worked'] }} hari kerja</p>
                                    </div>
                                    <div class="text-right text-xs text-slate-500">
                                        <p class="font-bold text-slate-900">{{ number_format($row['orders']) }} orders</p>
                                        <p>{{ number_format($row['items']) }} items</p>
                                    </div>
                                    <a href="{{ route('leaderboard.productivity.weekly.print', ['date' => $date->toDateString(), 'worker' => $row['key']]) }}" target="_blank" class="text-xs font-semibold text-blue-700">PDF</a>
                                </div>
                            @empty
                                <p class="py-6 text-center text-sm text-slate-500">Belum ada output minggu ini.</p>
                            @endforelse
                        </div>

                        <div class="mt-3 max-h-[24rem] divide-y divide-slate-100 overflow-y-auto border-t border-slate-100">
                            @foreach ($board['rows'] as $row)
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span class="w-6 text-center text-xs font-bold text-slate-500">{{ $row['rank'] }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $row['name'] }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $row['active_hours'] }} jam aktif &middot; {{ $row['orders_per_hour'] !== null ? number_format($row['orders_per_hour'], 1).' order/jam' : '-' }}</p>
                                    </div>
                                    <p class="text-sm font-bold tabular-nums text-slate-900">{{ number_format($row['orders']) }}</p>
                                    <a href="{{ route('leaderboard.productivity.weekly.print', ['date' => $date->toDateString(), 'worker' => $row['key']]) }}" target="_blank" class="text-xs font-semibold text-blue-700">PDF</a>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>

            <p class="mt-5 text-xs text-slate-500">
                Ranking diurutkan berdasarkan total orders selama 7 hari, lalu items sebagai tie-breaker. Jam aktif dan order/jam merupakan akumulasi dari productivity harian pada periode tersebut.
            </p>
        </section>
    </main>
@endsection
