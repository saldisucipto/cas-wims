@extends('layouts.operation')

@section('title', 'Manpower Planning Document - WIMS')

@section('content')
    @php
        $formatNumber = fn($value) => ($value == (int) $value) ? number_format((int) $value) : number_format($value, 2);
        $itemMppPerShift = fn($item) => $item->number_of_shift === 1 ? $item->required_mpp : $item->mpp_per_shift;
        $itemTotalMpp = fn($item) => $item->number_of_shift === 1 ? $item->required_mpp : ($item->mpp_per_shift * 2);
    @endphp

    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Manpower Planning Document</p>
                    <h1 class="wims-page-title">{{ $planning->planning_number }}</h1>
                    <p class="wims-page-subtitle">{{ \Carbon\Carbon::parse($planning->planning_date)->translatedFormat('d F Y') }}</p>
                    <p class="wims-breadcrumb">Administration / Reports / Manpower Planning</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if (! in_array($planning->status, ['FINAL', 'CANCELLED'], true))
                        <a href="{{ route('administration.manpower-planning.edit', $planning) }}" class="wims-btn wims-btn-primary">Edit</a>
                        <form action="{{ route('administration.manpower-planning.finalize', $planning) }}" method="POST">
                            @csrf
                            <button type="submit" class="wims-btn wims-btn-success">Finalize</button>
                        </form>
                    @endif
                    <form action="{{ route('administration.manpower-planning.duplicate', $planning) }}" method="POST">
                        @csrf
                        <button type="submit" class="wims-btn wims-btn-warning">Duplicate</button>
                    </form>
                    <a href="{{ route('administration.manpower-planning.print', $planning) }}" target="_blank"
                        class="wims-btn wims-btn-primary">Print / PDF</a>
                    <a href="{{ route('administration.manpower-planning.history') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            @if (session('error'))
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</p>
            @endif

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Planning No</p>
                    <p class="mt-1 text-lg font-bold text-slate-900">{{ $planning->planning_number }}</p>
                    <p class="mt-1 text-xs text-slate-500">Status:
                        <span class="font-semibold">{{ $planning->status }}</span>
                        @if ($planning->revision > 1)
                            &middot; Rev {{ $planning->revision }}
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-slate-500">Recommendation: <span class="font-semibold">{{ $planning->recommendation }}</span></p>
                    <p class="text-xs text-slate-500">Overall:
                        <span class="font-semibold {{ $planning->overall_status === 'CRITICAL' ? 'text-red-600' : 'text-emerald-600' }}">{{ $planning->overall_status }}</span>
                    </p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Workload</p>
                    <p class="mt-1 text-sm text-slate-700">Inbound: <span class="font-semibold">{{ $formatNumber($planning->inbound_volume) }} pcs</span></p>
                    <p class="text-sm text-slate-700">Outbound: <span class="font-semibold">{{ $formatNumber($planning->outbound_volume) }} order</span></p>
                    <p class="text-sm text-slate-700">VAS: <span class="font-semibold">{{ $formatNumber($planning->vas_volume) }} pcs</span></p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">Working Hours</p>
                    <p class="mt-1 text-sm text-slate-700">Shift Duration: <span class="font-semibold">{{ $formatNumber($planning->shift_duration) }} jam</span></p>
                    <p class="text-sm text-slate-700">Non Productive: <span class="font-semibold">{{ $formatNumber($planning->non_productive_hours) }} jam</span></p>
                    <p class="text-sm text-slate-700">Effective: <span class="font-semibold">{{ $formatNumber($planning->effective_working_hours) }} jam</span></p>
                </article>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <article class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-slate-500">Total Manpower</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ $formatNumber($planning->total_mpp) }} <span class="text-base font-semibold text-slate-500">MPP</span></p>
                </article>
                <article class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-slate-500">Total Device</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ $formatNumber($planning->devices->sum('physical_required')) }} <span class="text-base font-semibold text-slate-500">units</span></p>
                </article>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($divisions as $division)
                    <article class="rounded-xl border {{ $division['status'] === 'CRITICAL' ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-slate-50' }} p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-slate-900">{{ $division['division'] }}</h3>
                            @if ($division['status'] === 'CRITICAL')
                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">CRITICAL</span>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">FEASIBLE</span>
                            @endif
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                            <div>
                                <dt class="text-slate-500">MPP / Shift</dt>
                                <dd class="font-semibold text-slate-900">{{ $formatNumber($division['mpp_per_shift']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Shift</dt>
                                <dd class="font-semibold text-slate-900">{{ $division['shift'] === 0 ? 'Critical' : $division['shift'] }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500">Total MPP</dt>
                                <dd class="font-semibold text-slate-900">{{ $formatNumber($division['total_mpp']) }}</dd>
                            </div>
                        </dl>
                        @if (! empty($division['bottlenecks']))
                            <p class="mt-2 text-xs font-semibold text-amber-700">Bottleneck: {{ implode(', ', $division['bottlenecks']) }}</p>
                        @endif
                    </article>
                @endforeach
            </div>

            @if ($planning->devices->isNotEmpty())
                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
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
                                @foreach ($planning->devices as $device)
                                    <tr>
                                        <td class="font-semibold">{{ $device->device_type }}</td>
                                        <td>{{ $formatNumber($device->physical_required) }}</td>
                                        <td>{{ $formatNumber($device->ready_quantity) }}</td>
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

            <div class="mt-6 overflow-x-auto">
                <table class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>Division</th>
                            <th>Position</th>
                            <th>Workload</th>
                            <th>Productivity</th>
                            <th>Eff. Hrs</th>
                            <th>MPP / Shift</th>
                            <th>Shift</th>
                            <th>Total MPP</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($divisions as $division)
                            @foreach ($division['items'] as $item)
                                <tr>
                                    <td>{{ $item->division }}</td>
                                    <td class="font-semibold text-slate-900">{{ $item->name }}</td>
                                    <td>{{ $formatNumber($item->workload) }} {{ $item->workload_unit }}</td>
                                    <td>{{ $formatNumber($item->productivity_per_hour) }} {{ $item->productivity_unit }}</td>
                                    <td>{{ $formatNumber($item->effective_working_hours) }} jam</td>
                                    <td>{{ $formatNumber($itemMppPerShift($item)) }}</td>
                                    <td>{{ $item->number_of_shift === 0 ? '-' : $item->number_of_shift }}</td>
                                    <td>{{ $formatNumber($itemTotalMpp($item)) }}</td>
                                    <td>
                                        @if ($item->feasibility_status === 'Feasible')
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Feasible</span>
                                        @elseif ($item->feasibility_status === 'Feasible (2 Shift)')
                                            <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Feasible (2 Shift)</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Shortage</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-700">Created By</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $planning->creator?->name ?? '-' }} &middot; {{ $planning->created_at?->translatedFormat('d F Y H:i') }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-700">Updated By</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $planning->updater?->name ?? '-' }} &middot; {{ $planning->updated_at?->translatedFormat('d F Y H:i') }}</p>
                </article>
            </div>

            @if ($planning->notes)
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-700">Notes</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $planning->notes }}</p>
                </div>
            @endif
        </section>
    </main>
@endsection
