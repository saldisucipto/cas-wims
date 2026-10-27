@props(['productivity' => [], 'title' => null])

@php
    $hasSession = (bool) ($productivity['has_session'] ?? false);
    $hasData = (bool) ($productivity['has_data'] ?? false);
    $isStale = (bool) ($productivity['is_stale'] ?? true);
    $dataUntil = $productivity['data_until'] ?? null;
    $startedAt = $productivity['started_at'] ?? null;
    $role = $productivity['role'] ?? null;
    $title = $title ?? match ($role) {
        'picker' => 'Picker Productivity',
        'packer' => 'Packing Productivity',
        default => 'Shift Productivity',
    };
    $types = $productivity['transaction_types'] ?? null;
    $windowLabel = $startedAt
        ? $startedAt->copy()->timezone(config('app.timezone'))->format('H:i').' - '.(($productivity['ends_at'] ?? null)
            ? $productivity['ends_at']->copy()->timezone(config('app.timezone'))->format('H:i')
            : 'now')
        : '-';
@endphp

<section {{ $attributes->class(['rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40']) }}>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
            <p class="text-sm text-slate-500">
                Meson transactions recorded for this working session
                <span class="font-semibold text-slate-700">{{ $windowLabel }}</span>
                @if (is_array($types) && $types !== [])
                    <span class="block text-xs text-slate-400">Transaction type: {{ implode(', ', $types) }}</span>
                @endif
            </p>
        </div>
        @if ($hasSession && ($productivity['rank'] ?? null))
            <span class="inline-flex w-fit items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                Rank #{{ $productivity['rank'] }} of {{ $productivity['rank_total'] }}
            </span>
        @endif
    </div>

    @if ($isStale)
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span class="font-semibold">Meson data not synced.</span>
            Last import:
            {{ $dataUntil ? \Illuminate\Support\Carbon::parse($dataUntil)->format('d M Y H:i') : 'never' }}.
            Numbers below only cover transactions up to that date.
        </div>
    @endif

    <div class="mt-4 grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Items</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($productivity['items'] ?? 0) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-slate-500">Orders</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($productivity['orders'] ?? 0) }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <p class="text-slate-500">SKU Lines</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($productivity['lines'] ?? 0) }}</p>
        </div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
            <p class="text-blue-700">Items / Hour</p>
            <p class="mt-1 text-2xl font-bold text-blue-800">
                {{ ($productivity['items_per_hour'] ?? null) !== null ? number_format($productivity['items_per_hour'], 1) : '—' }}
            </p>
        </div>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3 text-sm lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <p class="text-slate-500">Orders / Hour</p>
            <p class="mt-1 font-semibold text-slate-900">
                {{ ($productivity['orders_per_hour'] ?? null) !== null ? number_format($productivity['orders_per_hour'], 1) : '—' }}
            </p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <p class="text-slate-500">Active Hours</p>
            <p class="mt-1 font-semibold text-slate-900">{{ number_format($productivity['active_hours'] ?? 0, 2) }} h</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <p class="text-slate-500">Session Duration</p>
            <p class="mt-1 font-semibold text-slate-900">{{ number_format($productivity['elapsed_hours'] ?? 0, 2) }} h</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3">
            <p class="text-slate-500">Data As Of</p>
            <p class="mt-1 font-semibold {{ $isStale ? 'text-amber-700' : 'text-slate-900' }}">
                {{ $dataUntil ? \Illuminate\Support\Carbon::parse($dataUntil)->format('d M Y') : '-' }}
            </p>
        </div>
    </div>

    @if (! $hasSession)
        <p class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            No working session is active for this employee.
        </p>
    @elseif (! $hasData)
        <p class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            No Meson transaction has been recorded for this session yet.
            @unless ($isStale)
                Waiting for the next data sync.
            @endunless
        </p>
    @endif
</section>
