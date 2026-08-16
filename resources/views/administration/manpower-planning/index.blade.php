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
                    Effective Working Hours: <span class="font-semibold text-slate-700">{{ $formatNumber($config['effective_hours']) }} jam / shift</span>
                    &middot; VAS volume tanggal ini: <span class="font-semibold text-slate-700">{{ $formatNumber($vasVolume) }} pcs</span>
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
                        <p class="text-sm text-slate-500">Recommended Shift</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">
                            {{ $result->recommendedShifts === 0 ? 'Critical' : $result->recommendedShifts.' Shift' }}
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
