@extends('layouts.operation')

@section('title', 'Manpower Activities - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Manpower Activities</h1>
                    <p class="mt-1 text-sm text-slate-600">Konfigurasi productivity, conversion, dan available manpower per process.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('administration.manpower-planning') }}"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Planning</a>
                    <a href="{{ route('administration.dashboard') }}"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
                </div>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}</p>
            @endif

            <form action="{{ route('administration.master.manpower-activities') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name/code/division..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-3">
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </form>

            <form action="{{ route('administration.master.manpower-activities.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <select name="division" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Inbound">Inbound</option>
                    <option value="Outbound">Outbound</option>
                </select>
                <input name="name" placeholder="Name (e.g. Picker)" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="code" placeholder="Code (e.g. OB-PICKER)" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="workload_source" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Inbound">Inbound Volume</option>
                    <option value="Outbound">Outbound Volume</option>
                    <option value="VAS">VAS Schedule</option>
                </select>
                <input name="workload_unit" placeholder="Workload Unit (PCS/Order)" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="conversion_ratio" type="number" step="0.01" min="0" placeholder="Conversion (1)" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="productivity_per_hour" type="number" step="0.01" min="0" placeholder="Productivity/hour" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="productivity_unit" placeholder="Prod. Unit (pcs/hour)" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="manpower_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Variable">Variable</option>
                    <option value="Hybrid">Hybrid</option>
                    <option value="Fixed">Fixed</option>
                </select>
                <input name="minimum_manpower" type="number" min="0" placeholder="Min MPP (Fixed/Hybrid)"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="available_manpower" type="number" min="0" placeholder="Available MPP" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="device_type" placeholder="Device (PC/RF, opsional)"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="allowed_shifts" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="S1,S2">S1 + S2</option>
                    <option value="S1">S1 only</option>
                    <option value="S2">S2 only</option>
                </select>
                <input name="start_time" type="time" value="07:00" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="end_time" type="time" value="23:00" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="sort_order" type="number" min="0" placeholder="Sort Order"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Active</span>
                </label>
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white md:col-span-4">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Division</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Source</th>
                            <th class="px-3 py-2">Conversion</th>
                            <th class="px-3 py-2">Productivity</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Available</th>
                            <th class="px-3 py-2">Device</th>
                            <th class="px-3 py-2">Window</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->division }}</td>
                                <td class="px-3 py-2 font-semibold">{{ $row->name }}</td>
                                <td class="px-3 py-2">{{ $row->workload_source }}</td>
                                <td class="px-3 py-2">{{ $row->conversion_ratio }} {{ $row->workload_unit }}</td>
                                <td class="px-3 py-2">{{ $row->productivity_per_hour }} {{ $row->productivity_unit }}</td>
                                <td class="px-3 py-2">{{ $row->manpower_type }}</td>
                                <td class="px-3 py-2">{{ $row->available_manpower }}</td>
                                <td class="px-3 py-2">{{ $row->device_type ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row->allowed_shifts }} ({{ $row->start_time }}-{{ $row->end_time }})</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.manpower-activities.update', $row) }}"
                                            method="POST" class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                                            @csrf
                                            <select name="division" class="rounded border border-slate-300 px-2 py-1">
                                                @foreach (['Inbound', 'Outbound'] as $division)
                                                    <option value="{{ $division }}" @selected($row->division === $division)>{{ $division }}</option>
                                                @endforeach
                                            </select>
                                            <input name="name" value="{{ $row->name }}" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="code" value="{{ $row->code }}" class="rounded border border-slate-300 px-2 py-1">
                                            <select name="workload_source" class="rounded border border-slate-300 px-2 py-1">
                                                @foreach (['Inbound' => 'Inbound Volume', 'Outbound' => 'Outbound Volume', 'VAS' => 'VAS Schedule'] as $value => $label)
                                                    <option value="{{ $value }}" @selected($row->workload_source === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input name="workload_unit" value="{{ $row->workload_unit }}" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="conversion_ratio" type="number" step="0.01" min="0" value="{{ $row->conversion_ratio }}" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="productivity_per_hour" type="number" step="0.01" min="0" value="{{ $row->productivity_per_hour }}" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="productivity_unit" value="{{ $row->productivity_unit }}" class="rounded border border-slate-300 px-2 py-1">
                                            <select name="manpower_type" class="rounded border border-slate-300 px-2 py-1">
                                                @foreach (['Variable', 'Hybrid', 'Fixed'] as $type)
                                                    <option value="{{ $type }}" @selected($row->manpower_type === $type)>{{ $type }}</option>
                                                @endforeach
                                            </select>
                                            <input name="minimum_manpower" type="number" min="0" value="{{ $row->minimum_manpower ?? '' }}" placeholder="Min MPP" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="available_manpower" type="number" min="0" value="{{ $row->available_manpower }}" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="device_type" value="{{ $row->device_type }}" placeholder="Device (PC/RF)" class="rounded border border-slate-300 px-2 py-1">
                                            <select name="allowed_shifts" class="rounded border border-slate-300 px-2 py-1">
                                                @foreach (['S1,S2' => 'S1 + S2', 'S1' => 'S1 only', 'S2' => 'S2 only'] as $value => $label)
                                                    <option value="{{ $value }}" @selected($row->allowed_shifts === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <input name="start_time" type="time" value="{{ $row->start_time }}" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="end_time" type="time" value="{{ $row->end_time }}" class="rounded border border-slate-300 px-2 py-1">
                                            <input name="sort_order" type="number" min="0" value="{{ $row->sort_order }}" class="rounded border border-slate-300 px-2 py-1">
                                            <label class="flex items-center gap-2 rounded border border-slate-300 px-2 py-1">
                                                <input type="checkbox" name="is_active" value="1" @checked($row->is_active)>
                                                <span>Active</span>
                                            </label>
                                            <button class="rounded bg-blue-700 px-2 py-1 text-white md:col-span-3">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.manpower-activities.delete', $row) }}"
                                        method="POST" class="mt-2"
                                        onsubmit="return confirm('Delete this manpower activity?');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
    </main>
@endsection
