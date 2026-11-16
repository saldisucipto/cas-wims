@extends('layouts.operation')

@section('title', 'Manpower Planning - WIMS')

@section('content')
    @php
        $formatNumber = fn($value) => ($value == (int) $value) ? number_format((int) $value) : number_format($value, 2);
    @endphp

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Reports</p>
                    <h1 class="wims-page-title">Manpower Planning</h1>
                    <p class="wims-page-subtitle">Determine manpower requirement, shift feasibility, and bottleneck per process.</p>
                    <p class="wims-breadcrumb">Administration / Reports / Manpower Planning</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('administration.manpower-planning.history') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">History</a>
                    <a href="{{ route('administration.master.manpower-activities') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Master Activities</a>
                    <a href="{{ route('administration.dashboard') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            <form action="{{ route('administration.manpower-planning') }}" method="GET"
                class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div>
                        <label for="inbound_volume" class="mb-1 block text-sm font-semibold text-slate-700">Inbound Volume (pcs)</label>
                        <input id="inbound_volume" name="inbound_volume" type="number" min="0"
                            value="{{ $inboundVolume }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="10000">
                    </div>
                    <div>
                        <label for="outbound_volume" class="mb-1 block text-sm font-semibold text-slate-700">Outbound Volume (order)</label>
                        <input id="outbound_volume" name="outbound_volume" type="number" min="0"
                            value="{{ $outboundVolume }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="5000">
                    </div>
                    <div>
                        <label for="date" class="mb-1 block text-sm font-semibold text-slate-700">Tanggal</label>
                        <input id="date" name="date" type="date" value="{{ $date }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="wims-btn wims-btn-primary w-full">Hitung</button>
                    </div>
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    Jam kerja efektif: <span class="font-semibold text-slate-700">{{ $formatNumber($config['effective_hours']) }} jam / shift</span>
                    @php
                        $defaultShift = collect($config['shifts'] ?? [])->firstWhere('is_short_day', false);
                    @endphp
                    @if ($defaultShift)
                        ({{ $defaultShift['start_time'] }}&ndash;{{ $defaultShift['end_time'] }} =
                        {{ $formatNumber($defaultShift['wall_hours']) }} jam,
                        dikurangi istirahat {{ $formatNumber($defaultShift['break_minutes'] / 60) }} jam)
                    @endif
                    &middot; VAS volume tanggal ini: <span class="font-semibold text-slate-700">{{ $formatNumber($vasVolume) }} pcs</span>
                    &middot; <a href="{{ route('administration.system.shift-settings') }}" class="text-blue-700 underline">Ubah di Shift Settings</a>
                    &middot; <a href="{{ route('administration.master.manpower-vas-schedules') }}" class="text-blue-700 underline">Kelola VAS Schedule</a>
                </p>
            </form>

            @if (! $result)
                <div class="mt-6">
                    <div class="wims-empty-state">Masukkan Inbound dan Outbound volume, lalu tekan Hitung untuk melihat hasil manpower planning.</div>
                </div>
            @else
                <div class="mt-6">
                    <h2 class="text-lg font-semibold text-slate-900">Hasil Manpower Planning</h2>
                    <p class="text-sm text-slate-600">{{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}</p>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">Jadwal Shift</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">
                            {{ $result->recommendedShifts === 0 ? 'Critical' : $result->recommendedShifts.' Shift' }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Shift operasional yang harus dibuka (Dispatch &amp; Handover hanya ada di shift malam).
                        </p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">Kebutuhan Kapasitas</p>
                        <p class="mt-1 text-2xl font-bold {{ ($result->capacityShifts['Outbound']['shifts'] ?? 1) > 1 ? 'text-amber-600' : 'text-emerald-600' }}">
                            {{ $result->capacityShifts['Outbound']['shifts'] ?? 1 }} Shift
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Outbound masih muat 1 shift bila kapasitas standar dan perangkat siap mencukupi.
                        </p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">Manpower</p>
                        <p class="mt-1 text-2xl font-bold {{ $result->manpowerFeasible ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $result->manpowerFeasible ? '✓ FEASIBLE' : '✕ NOT FEASIBLE' }}
                        </p>
                    </article>
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-500">Device</p>
                        <p class="mt-1 text-2xl font-bold {{ $result->deviceFeasible ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $result->deviceFeasible ? '✓ FEASIBLE' : '✕ NOT FEASIBLE' }}
                        </p>
                    </article>
                </div>

                @if (! empty($result->capacityShifts))
                    @php
                        $packingSummary = collect($result->capacityShifts)
                            ->flatMap(fn ($division) => $division['activities'])
                            ->firstWhere('code', 'OB-CHECKPACK');
                    @endphp

                    @if ($packingSummary)
                        @php
                            $packingMpp = (int) $packingSummary['required_one_shift'];
                            $packingStations = $packingSummary['ready_devices'];
                            $packingCapacity = $packingSummary['capacity_per_person'];
                            $packingShort = $packingStations !== null && $packingStations > 0 && $packingMpp > $packingStations;
                        @endphp
                        <div class="mt-4 rounded-xl border {{ $packingShort ? 'border-amber-300 bg-amber-50' : 'border-emerald-300 bg-emerald-50' }} p-4">
                            <p class="text-sm font-semibold {{ $packingShort ? 'text-amber-800' : 'text-emerald-800' }}">
                                MPP Check-Pack (standar 24 order/jam)
                            </p>
                            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div>
                                    <p class="text-[11px] {{ $packingShort ? 'text-amber-700' : 'text-emerald-700' }}">Kapasitas 1 orang / shift</p>
                                    <p class="text-lg font-bold {{ $packingShort ? 'text-amber-900' : 'text-emerald-900' }}">
                                        {{ $formatNumber($packingCapacity) }} order
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[11px] {{ $packingShort ? 'text-amber-700' : 'text-emerald-700' }}">MPP 1 shift</p>
                                    <p class="text-lg font-bold {{ $packingShort ? 'text-amber-900' : 'text-emerald-900' }}">
                                        {{ $formatNumber($packingMpp) }} packer
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[11px] {{ $packingShort ? 'text-amber-700' : 'text-emerald-700' }}">MPP per shift (2 shift)</p>
                                    <p class="text-lg font-bold {{ $packingShort ? 'text-amber-900' : 'text-emerald-900' }}">
                                        {{ $formatNumber($packingSummary['required_per_shift']) }} packer
                                    </p>
                                </div>
                                <div>
                                    <p class="text-[11px] {{ $packingShort ? 'text-amber-700' : 'text-emerald-700' }}">
                                        Meja packing ready{{ $packingSummary['device_type'] ? ' ('.$packingSummary['device_type'].')' : '' }}
                                    </p>
                                    <p class="text-lg font-bold {{ $packingShort ? 'text-amber-900' : 'text-emerald-900' }}">
                                        {{ $packingStations !== null ? $formatNumber($packingStations).' meja' : '—' }}
                                    </p>
                                </div>
                            </div>
                            @if ($packingShort)
                                <p class="mt-2 text-xs font-semibold text-amber-800">
                                    {{ $formatNumber($packingMpp) > $packingStations ? 'Tidak muat 1 shift' : 'Muat 1 shift' }}:
                                    butuh {{ $formatNumber($packingMpp) }} packer sedangkan meja hanya {{ $formatNumber($packingStations) }}.
                                    Pilihan: pecah ke 2 shift ({{ $formatNumber($packingSummary['required_per_shift']) }} packer per shift)
                                    atau tambah meja / naikkan standar.
                                </p>
                            @else
                                <p class="mt-2 text-xs font-semibold text-emerald-800">
                                    Muat 1 shift: {{ $formatNumber($packingMpp) }} packer &le; {{ $formatNumber($packingStations) }} meja ready.
                                </p>
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-700">Kebutuhan Shift per Aktivitas</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Dihitung dari beban kerja dibagi kapasitas standar per orang dan perangkat yang ready,
                            tanpa terikat jam buka divisi.
                        </p>
                        @foreach ($result->capacityShifts as $division => $summary)
                            <div class="mt-3 overflow-x-auto">
                                <table class="wims-table min-w-full text-left text-sm">
                                    <thead>
                                        <tr>
                                            <th>{{ $division }}</th>
                                            <th>Kapasitas/orang</th>
                                            <th>MPP 1 shift</th>
                                            <th>MPP per shift (2 shift)</th>
                                            <th>Device ready</th>
                                            <th>Shift dibutuhkan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($summary['activities'] as $activity)
                                            @php
                                                $overCapacity = $activity['ready_devices'] !== null
                                                    && $activity['ready_devices'] > 0
                                                    && $activity['required_one_shift'] > $activity['ready_devices'];
                                            @endphp
                                            <tr>
                                                <td class="font-semibold text-slate-900">{{ $activity['name'] }}</td>
                                                <td>{{ $activity['capacity_per_person'] !== null ? $formatNumber($activity['capacity_per_person']) : '—' }}</td>
                                                <td class="{{ $overCapacity ? 'font-bold text-red-600' : '' }}">
                                                    {{ $formatNumber($activity['required_one_shift']) }}
                                                </td>
                                                <td>{{ $formatNumber($activity['required_per_shift']) }}</td>
                                                <td>
                                                    @if ($activity['ready_devices'] !== null && $activity['ready_devices'] > 0)
                                                        <span class="{{ $overCapacity ? 'font-semibold text-red-600' : 'text-slate-600' }}">
                                                            {{ $formatNumber($activity['ready_devices']) }}
                                                            @if ($activity['device_type'])
                                                                {{ $activity['device_type'] }}
                                                            @endif
                                                        </span>
                                                    @else
                                                        <span class="text-slate-400">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                        style="{{ $activity['shifts'] > 1 ? 'background:#fef3c7;color:#92400e' : 'background:#dcfce7;color:#166534' }}">
                                                        {{ $activity['shifts'] }} shift
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($recommendations)
                    @php
                        $sourceBadge = function (string $source) {
                            return match ($source) {
                                'measured' => 'background:#dcfce7;color:#166534',
                                'standard' => 'background:#fef3c7;color:#92400e',
                                default => 'background:#fee2e2;color:#991b1b',
                            };
                        };
                    @endphp

                    <div class="mt-6">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Rekomendasi Tenaga Kerja</h2>
                                <p class="text-sm text-slate-600">
                                    Picker dan packer dihitung terpisah: picker dalam pcs, packer dalam order.
                                </p>
                            </div>
                            <p class="text-xs text-slate-500">
                                Basis data Meson:
                                <span class="font-semibold text-slate-700">
                                    {{ $recommendations['period']['from']?->format('d M Y') ?? '-' }}
                                    @if ($recommendations['period']['to'] && $recommendations['period']['from'] && ! $recommendations['period']['to']->isSameDay($recommendations['period']['from']))
                                        &ndash; {{ $recommendations['period']['to']->format('d M Y') }}
                                    @endif
                                </span>
                                @unless ($recommendations['period']['is_exact_previous_day'] ?? true)
                                    <span class="text-amber-700">(tidak ada data H-1, memakai hari terakhir tersedia)</span>
                                @endunless
                                &middot; Outbound: <span class="font-semibold text-slate-700">{{ $formatNumber($outboundVolume) }} order</span>
                                &middot; <span class="font-semibold text-slate-700">{{ $formatNumber($config['effective_hours']) }} jam/shift</span>
                            </p>
                        </div>

                        @if (! $recommendations['has_measured_data'])
                            <div class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                <span class="font-semibold">Belum ada produktivitas terukur yang memenuhi syarat.</span>
                                Rekomendasi di bawah memakai standar master activity sebagai perkiraan. Import data Meson
                                agar saran dihitung dari performa pekerja sebenarnya.
                            </div>
                        @endif

                        <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">
                            @foreach (['picker', 'packer'] as $roleKey)
                                @php
                                    $rec = $recommendations[$roleKey];
                                    $isPicker = $roleKey === 'picker';
                                    $accent = $isPicker ? 'border-blue-200' : 'border-emerald-200';
                                    $accentText = $isPicker ? 'text-blue-700' : 'text-emerald-700';
                                    $accentBg = $isPicker ? 'bg-blue-50' : 'bg-emerald-50';
                                    $strip = $isPicker ? 'bg-blue-600' : 'bg-emerald-600';
                                @endphp

                                <article class="overflow-hidden rounded-xl border {{ $accent }} bg-white shadow-sm shadow-slate-300/30">
                                    <div class="h-1 w-full {{ $strip }}"></div>

                                    <div class="flex items-start justify-between gap-3 px-5 pt-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rekomendasi</p>
                                            <h3 class="text-base font-bold text-slate-900">{{ $rec['label'] }} <span class="text-xs font-semibold text-slate-500">({{ $rec['unit'] }})</span></h3>
                                        </div>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                            style="{{ $sourceBadge($rec['source']) }}">
                                            {{ $rec['source_label'] }}
                                        </span>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-2 px-5 sm:grid-cols-4">
                                        <div class="rounded-lg border {{ $accent }} {{ $accentBg }} px-3 py-2">
                                            <p class="text-[11px] text-slate-500">Jumlah orang</p>
                                            <p class="text-2xl font-bold {{ $accentText }}">
                                                {{ $rec['headcount'] ?? '—' }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                            <p class="text-[11px] text-slate-500">Kapasitas / orang</p>
                                            <p class="text-lg font-bold text-slate-900">
                                                {{ $rec['capacity_per_person'] !== null ? $formatNumber($rec['capacity_per_person']) : '—' }}
                                                <span class="text-[11px] font-normal text-slate-500">{{ $rec['unit'] }}</span>
                                            </p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                            <p class="text-[11px] text-slate-500">Selesai dalam</p>
                                            <p class="text-lg font-bold text-slate-900">
                                                {{ $rec['projected_hours'] !== null ? $formatNumber($rec['projected_hours']).' jam' : '—' }}
                                            </p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                            <p class="text-[11px] text-slate-500">Coverage</p>
                                            <p class="text-lg font-bold {{ ($rec['coverage'] ?? 0) >= 100 ? 'text-emerald-600' : 'text-amber-600' }}">
                                                {{ $rec['headcount'] !== null ? $formatNumber($rec['coverage']).'%' : '—' }}
                                            </p>
                                        </div>
                                    </div>

                                    <p class="mt-3 px-5 text-[11px] text-slate-500">
                                        Demand:
                                        <span class="font-semibold text-slate-700">{{ $formatNumber($rec['demand']) }} {{ $rec['unit'] }}</span>
                                        @if ($isPicker && isset($rec['pieces_per_order']))
                                            ({{ $formatNumber($rec['demand_orders']) }} order &times; {{ $formatNumber($rec['pieces_per_order']) }} pcs/order)
                                        @endif
                                        &middot; Standar:
                                        <span class="font-semibold text-slate-700">{{ $formatNumber($rec['standard_rate'] ?? 0) }} {{ $rec['standard_unit'] ?? 'order/jam' }}</span>
                                        &middot; Rata-rata kandidat:
                                        <span class="font-semibold text-slate-700">{{ $rec['pool_rate'] !== null ? $formatNumber($rec['pool_rate']).' '.$rec['unit_label'] : '—' }}</span>
                                        &middot; Allowance <span class="font-semibold text-slate-700">{{ $formatNumber($rec['allowance'] * 100) }}%</span>
                                    </p>

                                    <div class="mt-3 grid grid-cols-2 gap-2 px-5 sm:grid-cols-4">
                                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                                            <p class="text-[11px] text-emerald-700">Sudah di atas standar</p>
                                            <p class="text-lg font-bold text-emerald-700">{{ $rec['at_standard'] ?? 0 }} orang</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                            <p class="text-[11px] text-slate-500">Kandidat terukur</p>
                                            <p class="text-lg font-bold text-slate-900">{{ $rec['ranked_pool'] ?? 0 }} orang</p>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                            <p class="text-[11px] text-slate-500">Kebutuhan jika pas standar</p>
                                            <p class="text-lg font-bold text-slate-900">{{ $rec['standard_headcount'] ?? '—' }} orang</p>
                                        </div>
                                        <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                                            <p class="text-[11px] text-blue-700">Rekomendasi shift</p>
                                            <p class="text-lg font-bold text-blue-700">
                                                {{ $rec['recommended_shift'] ?? '—' }} shift
                                                <span class="text-[11px] font-normal text-blue-600">{{ $formatNumber($rec['effective_hours'] ?? 0) }} jam</span>
                                            </p>
                                        </div>
                                    </div>

                                    @if (! empty($rec['shift_plan']))
                                        <div class="mt-3 px-5">
                                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Perbandingan shift</p>
                                            <div class="mt-1 overflow-x-auto">
                                                <table class="wims-table min-w-full text-left text-xs">
                                                    <thead>
                                                        <tr>
                                                            <th>Skenario</th>
                                                            <th>{{ $rec['unit'] }}/shift</th>
                                                            <th>Kapasitas/orang</th>
                                                            <th>Kebutuhan orang</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($rec['shift_plan'] as $scenario)
                                                            <tr class="{{ $scenario['shifts'] === ($rec['recommended_shift'] ?? null) ? 'bg-emerald-50' : '' }}">
                                                                <td class="font-semibold">{{ $scenario['label'] }}</td>
                                                                <td>{{ $formatNumber($scenario['orders_per_shift']) }}</td>
                                                                <td>{{ $formatNumber($scenario['capacity_per_person']) }}</td>
                                                                <td class="font-semibold">{{ $scenario['headcount'] }} orang</td>
                                                                <td>
                                                                    @if ($scenario['feasible'])
                                                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background:#dcfce7;color:#166534">MUAT</span>
                                                                    @else
                                                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background:#fee2e2;color:#991b1b">
                                                                            TIDAK MUAT{{ $scenario['payload'] > 0 ? ' (maks '.$scenario['payload'].' orang)' : '' }}
                                                                        </span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endif

                                    @if (! empty($rec['selected']))
                                        <div class="mt-4 overflow-x-auto border-t border-slate-100">
                                            <table class="wims-table min-w-full text-left text-sm">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Nama</th>
                                                        <th>{{ $rec['unit_label'] }}</th>
                                                        <th>Order/jam</th>
                                                        <th>Vs standar</th>
                                                        <th>Kapasitas/shift</th>
                                                        <th>Alokasi {{ $rec['unit'] }}</th>
                                                        <th>Utilisasi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($rec['selected'] as $index => $worker)
                                                        <tr>
                                                            <td class="font-semibold text-slate-500">{{ $index + 1 }}</td>
                                                            <td>
                                                                <span class="font-semibold text-slate-900">{{ $worker['name'] }}</span>
                                                                <div class="text-[11px] text-slate-500">
                                                                    {{ $worker['employee_code'] ?? '-' }} &middot; {{ $worker['function'] }}
                                                                    &middot; {{ $worker['days_worked'] }} hari data
                                                                </div>
                                                            </td>
                                                            <td>{{ $formatNumber($worker['units_per_hour']) }}</td>
                                                            <td>{{ isset($worker['orders_per_hour']) ? $formatNumber($worker['orders_per_hour']) : '—' }}</td>
                                                            <td>
                                                                @if ($worker['meets_standard'] ?? false)
                                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background:#dcfce7;color:#166534">
                                                                        ✓ +{{ $formatNumber($worker['gap_percent'] ?? 0) }}%
                                                                    </span>
                                                                @else
                                                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background:#fef3c7;color:#92400e">
                                                                        {{ $formatNumber($worker['gap_percent'] ?? 0) }}%
                                                                    </span>
                                                                @endif
                                                            </td>
                                                            <td>{{ $formatNumber($worker['capacity']) }}</td>
                                                            <td class="font-semibold">{{ $formatNumber($worker['allocated_units']) }}</td>
                                                            <td>{{ $formatNumber($worker['utilization']) }}%</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @if (! empty($rec['near_standard']))
                                        <div class="border-t border-slate-100">
                                            <details class="group">
                                                <summary class="cursor-pointer px-5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                    Peringkat menuju standar {{ $formatNumber($rec['standard_rate'] ?? 0) }} {{ $rec['standard_unit'] ?? 'order/jam' }}
                                                    <span class="font-normal text-slate-500">({{ count($rec['near_standard']) }} orang, terdekat lebih dulu)</span>
                                                </summary>
                                                <div class="max-h-80 overflow-y-auto overflow-x-auto">
                                                    <table class="wims-table min-w-full text-left text-xs">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Nama</th>
                                                                <th>Order/jam</th>
                                                                <th>Gap</th>
                                                                <th>Hari data</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($rec['near_standard'] as $rank => $candidate)
                                                                <tr>
                                                                    <td class="font-semibold text-slate-500">{{ $rank + 1 }}</td>
                                                                    <td>
                                                                        <span class="font-semibold text-slate-900">{{ $candidate['name'] }}</span>
                                                                        <div class="text-[11px] text-slate-500">{{ $candidate['employee_code'] ?? '-' }} &middot; {{ $candidate['function'] ?? '-' }}</div>
                                                                    </td>
                                                                    <td class="font-semibold">{{ $formatNumber($candidate['order_rate'] ?? 0) }}</td>
                                                                    <td>
                                                                        @if ($candidate['meets_standard'] ?? false)
                                                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background:#dcfce7;color:#166534">
                                                                                ✓ +{{ $formatNumber($candidate['gap_percent'] ?? 0) }}%
                                                                            </span>
                                                                        @else
                                                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background:#fee2e2;color:#991b1b">
                                                                                {{ $formatNumber($candidate['gap_percent'] ?? 0) }}%
                                                                            </span>
                                                                        @endif
                                                                    </td>
                                                                    <td>{{ $candidate['days_worked'] ?? 0 }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </details>
                                        </div>
                                    @endif

                                    @if ($rec['note'])
                                        <div class="border-t border-amber-200 bg-amber-50 px-5 py-2.5 text-xs text-amber-800">
                                            {{ $rec['note'] }}
                                        </div>
                                    @endif

                                    <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-5 py-2 text-[11px] text-slate-500">
                                        <span>
                                            {{ $rec['pool_size'] }} kandidat terukur
                                            @if ($rec['untracked_pool'] > 0)
                                                &middot; {{ $rec['untracked_pool'] }} tanpa data cukup
                                            @endif
                                        </span>
                                        <span>
                                            {{ $rec['shortfall'] > 0 ? 'Kurang '.$formatNumber($rec['shortfall']).' '.$rec['unit'] : 'Demand terpenuhi' }}
                                        </span>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <p class="mt-3 text-xs text-slate-500">
                            Standar produktivitas: packer {{ $formatNumber($recommendations['packer']['standard_rate'] ?? 0) }} order/jam,
                            picker {{ $formatNumber($recommendations['picker']['standard_rate'] ?? 0) }} order/jam.
                            Jam efektif diambil dari Shift Definitions di
                            <span class="font-semibold">/administration/system/shift-settings</span>
                            (Shift 1 {{ $formatNumber($recommendations['picker']['effective_hours'] ?? 0) }} jam,
                            Shift 2 {{ $formatNumber($recommendations['packer']['effective_hours'] ?? 0) }} jam), bukan dari setelan lama.
                            Jam tersebut sudah dikurangi waktu istirahat: rentang 08:00&ndash;17:00 terhitung 9 jam, dikurangi
                            1 jam istirahat menjadi 8 jam kerja efektif.
                            Rekomendasi shift: satu shift dipakai selama jumlah orang masih muat di kapasitas shift;
                            kalau tidak muat, order dibagi ke dua shift.
                            Urutan kandidat: yang sudah menyentuh atau melewati standar tampil paling atas (rate tertinggi dulu),
                            lalu sisanya dari yang paling dekat ke standar.
                            Rate diambil dari hari kerja terakhir yang punya data Meson (maksimal 3 hari), memakai median
                            kandidat terukur, bukan satu pekerja tercepat. Picker diukur dari transaksi
                            <span class="font-semibold">Picking&amp;Packing</span> dalam satuan pcs, packer dari transaksi
                            <span class="font-semibold">Packing</span> dalam satuan order; keduanya tidak digabung. Kapasitas
                            per orang dikurangi allowance {{ $formatNumber(($recommendations['picker']['allowance'] ?? 0) * 100) }}%
                            untuk break dan waktu tunggu. Kandidat dengan data kurang dari 10 unit atau 0,5 jam kerja tidak dipakai.
                        </p>
                    </div>
                @endif

                @if (! empty($result->devices))
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-700">Device Readiness</p>
                        <div class="mt-2 overflow-x-auto">
                            <table class="wims-table min-w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        <th>Device</th>
                                        <th>Required</th>
                                        <th>Ready</th>
                                        <th>Shortage</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($result->devices as $device)
                                        <tr>
                                            <td class="font-semibold">{{ $device->deviceType }}</td>
                                            <td>{{ $formatNumber($device->physicalRequired) }}</td>
                                            <td>{{ $formatNumber($device->readyQuantity) }}</td>
                                            <td>{{ $formatNumber($device->shortage) }}</td>
                                            <td>
                                                @if ($device->status === 'FEASIBLE')
                                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">FEASIBLE</span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">SHORTAGE</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (! empty($result->manpowerBottlenecks) || ! empty($result->deviceBottlenecks))
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <p class="font-semibold">Bottleneck:</p>
                        @if (! empty($result->manpowerBottlenecks))
                            <p>Manpower: {{ implode(', ', $result->manpowerBottlenecks) }}</p>
                        @endif
                        @if (! empty($result->deviceBottlenecks))
                            <p>Device: {{ implode(', ', $result->deviceBottlenecks) }}</p>
                        @endif
                    </div>
                @endif

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ($result->divisions as $division)
                        <article class="rounded-xl border {{ $division->status === 'CRITICAL' ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-slate-50' }} p-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-bold text-slate-900">{{ $division->division }}</h3>
                                @if ($division->status === 'CRITICAL')
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">CRITICAL</span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">FEASIBLE</span>
                                @endif
                            </div>

                            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <dt class="text-slate-500">Volume</dt>
                                    <dd class="font-semibold text-slate-900">{{ $formatNumber($division->sourceVolume) }} {{ $division->sourceUnit }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Recommended Shift</dt>
                                    <dd class="font-semibold text-slate-900">
                                        @if ($division->recommendedShifts === 0)
                                            Tidak Feasible
                                        @else
                                            {{ $division->recommendedShifts }} Shift
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">{{ $division->recommendedShifts === 1 ? 'Total MPP' : 'MPP / Shift' }}</dt>
                                    <dd class="font-semibold text-slate-900">
                                        {{ $formatNumber($division->recommendedShifts === 1 ? $division->totalMppOneShift : $division->totalMppPerShift) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Status</dt>
                                    <dd class="font-semibold text-slate-900">{{ $division->status }}</dd>
                                </div>
                            </dl>

                            @if (! empty($division->bottlenecks))
                                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    <p class="font-semibold">Bottleneck: {{ implode(', ', $division->bottlenecks) }}</p>
                                    @foreach ($division->activities as $activity)
                                        @if ($activity->isWorkloadDriven && ! $activity->oneShiftFeasible)
                                            <p class="mt-1">{{ $activity->name }}: 1 shift butuh {{ $formatNumber($activity->requiredOneShift) }} MPP, tersedia {{ $formatNumber($activity->availableManpower) }} MPP.</p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @if ($division->status === 'CRITICAL')
                                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                                    <p class="font-semibold">2 Shift is NOT FEASIBLE.</p>
                                    @foreach ($division->activities as $activity)
                                        @if ($activity->isWorkloadDriven && ! $activity->twoShiftFeasible)
                                            <p class="mt-1">{{ $activity->name }}: kekurangan {{ $formatNumber($activity->shortagePerShift) }} MPP/shift.</p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                @foreach ($result->divisions as $division)
                    <div class="mt-6">
                        <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">{{ $division->division }}</h3>
                        <div class="mt-2 overflow-x-auto">
                            <table class="wims-table min-w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        <th>Position</th>
                                        <th>Type</th>
                                        <th>Workload</th>
                                        <th>Productivity</th>
                                        <th>Req MPP (1 Shift)</th>
                                        <th>Req MPP / Shift (2)</th>
                                        <th>Available</th>
                                        <th>Shift</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($division->activities as $activity)
                                        <tr>
                                            <td class="font-semibold text-slate-900">{{ $activity->name }}</td>
                                            <td>{{ $activity->manpowerType }}</td>
                                            <td>{{ $formatNumber($activity->workload) }} {{ $activity->workloadUnit }}</td>
                                            <td>{{ $formatNumber($activity->productivityPerHour) }} {{ $activity->productivityUnit }}</td>
                                            <td>{{ $formatNumber($activity->requiredOneShift) }}</td>
                                            <td>{{ $activity->requiredTwoShifts >= PHP_INT_MAX ? '∞' : $formatNumber($activity->requiredTwoShifts) }}</td>
                                            <td>{{ $formatNumber($activity->availableManpower) }}</td>
                                            <td>{{ $activity->recommendedShifts === 0 ? '-' : $activity->recommendedShifts }}</td>
                                            <td>
                                                @if ($activity->status === 'Feasible')
                                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Feasible</span>
                                                @elseif ($activity->status === 'Feasible (2 Shift)')
                                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Feasible (2 Shift)</span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Shortage</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-700">Simpan Planning</p>
                    <form action="{{ route('administration.manpower-planning.store') }}" method="POST"
                        class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
                        @csrf
                        <input type="hidden" name="inbound_volume" value="{{ $inboundVolume }}">
                        <input type="hidden" name="outbound_volume" value="{{ $outboundVolume }}">
                        <input type="hidden" name="planning_date" value="{{ $date }}">
                        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach (['CALCULATED', 'DRAFT', 'FINAL'] as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                        <input name="notes" placeholder="Notes (opsional)"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                        <button type="submit" class="wims-btn wims-btn-success">Save Planning</button>
                    </form>
                </div>
            @endif
        </section>
    </main>
@endsection
