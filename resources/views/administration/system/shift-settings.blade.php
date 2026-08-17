@extends('layouts.operation')

@section('title', 'Shift Settings - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">System</p>
                <h1 class="text-2xl font-bold text-slate-900">Shift Settings</h1>
                <p class="mt-1 text-sm text-slate-600">Definisi shift, jam kerja mingguan, dan cut-off operasional BCO.</p>
            </div>
            <a href="{{ route('administration.dashboard') }}"
                class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
        </div>

        @if (session('success'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}</div>
        @endif

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <h2 class="text-base font-bold text-slate-900">Shift Definitions</h2>
            <p class="mt-1 text-sm text-slate-600">Jam kerja, istirahat, dan jam efektif untuk Shift 1, Shift 2, dan short shift Sabtu.</p>

            <form method="POST" action="{{ route('administration.system.shift-definitions.save') }}" class="mt-4">
                @csrf
                <div class="overflow-x-auto">
                    <table class="wims-table min-w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th>Shift</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Break Start</th>
                                <th>Break End</th>
                                <th>Effective</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($definitions as $definition)
                                <tr>
                                    <td class="font-semibold text-slate-900">
                                        {{ $definition->name }}
                                        @if ($definition->is_short_day)
                                            <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Short</span>
                                        @endif
                                    </td>
                                    <td><input type="time" name="start_time[{{ $definition->id }}]" value="{{ $definition->start_time }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></td>
                                    <td><input type="time" name="end_time[{{ $definition->id }}]" value="{{ $definition->end_time }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></td>
                                    <td><input type="time" name="break_start[{{ $definition->id }}]" value="{{ $definition->break_start }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></td>
                                    <td><input type="time" name="break_end[{{ $definition->id }}]" value="{{ $definition->break_end }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></td>
                                    <td class="font-semibold">{{ $definition->effective_hours }}h</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save Shift Definitions</button>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <h2 class="text-base font-bold text-slate-900">Working Hour &amp; BCO Configuration</h2>
            <p class="mt-1 text-sm text-slate-600">Batas jam kerja mingguan dan cut-off operasional BCO (belum ditetapkan final, isi bila diperlukan).</p>

            <form method="POST" action="{{ route('administration.system.shift-settings.save') }}"
                class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700">Morning Shift Start</label>
                    <input type="text" name="morning_shift_start"
                        value="{{ old('morning_shift_start', $settings['morning_shift_start'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="07:00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Morning Shift End</label>
                    <input type="text" name="morning_shift_end"
                        value="{{ old('morning_shift_end', $settings['morning_shift_end'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="16:00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Night Shift Start</label>
                    <input type="text" name="night_shift_start"
                        value="{{ old('night_shift_start', $settings['night_shift_start'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="16:00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Night Shift End</label>
                    <input type="text" name="night_shift_end"
                        value="{{ old('night_shift_end', $settings['night_shift_end'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="01:00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Shift Duration (jam)</label>
                    <input type="number" step="0.5" name="shift_duration"
                        value="{{ old('shift_duration', $settings['shift_duration'] ?? '8') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="8">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Non Productive Hours (jam)</label>
                    <input type="number" step="0.5" name="non_productive_hours"
                        value="{{ old('non_productive_hours', $settings['non_productive_hours'] ?? '1') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="1">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Effective Working Hours (per shift)</label>
                    <input type="number" step="0.5" name="effective_working_hours"
                        value="{{ old('effective_working_hours', $settings['effective_working_hours'] ?? '7') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="7">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Max Weekly Working Hours</label>
                    <input type="number" step="0.5" name="max_weekly_hours"
                        value="{{ old('max_weekly_hours', $settings['max_weekly_hours'] ?? '40') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="40">
                </div>

                <div class="md:col-span-2 mt-2 border-t border-slate-200 pt-4">
                    <h3 class="text-sm font-bold text-slate-800">BCO Operational Cut-off (opsional)</h3>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Order Release Cut-off</label>
                    <input type="time" name="bco_order_release_cutoff"
                        value="{{ old('bco_order_release_cutoff', $settings['bco_order_release_cutoff'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Picking Cut-off</label>
                    <input type="time" name="bco_picking_cutoff"
                        value="{{ old('bco_picking_cutoff', $settings['bco_picking_cutoff'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Packing Cut-off</label>
                    <input type="time" name="bco_packing_cutoff"
                        value="{{ old('bco_packing_cutoff', $settings['bco_packing_cutoff'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">QC Cut-off</label>
                    <input type="time" name="bco_qc_cutoff"
                        value="{{ old('bco_qc_cutoff', $settings['bco_qc_cutoff'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Ready to Ship Cut-off</label>
                    <input type="time" name="bco_ready_to_ship_cutoff"
                        value="{{ old('bco_ready_to_ship_cutoff', $settings['bco_ready_to_ship_cutoff'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Expedition Handover Cut-off</label>
                    <input type="time" name="bco_expedition_handover_cutoff"
                        value="{{ old('bco_expedition_handover_cutoff', $settings['bco_expedition_handover_cutoff'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div class="md:col-span-2">
                    <button type="submit"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save</button>
                </div>
            </form>
        </section>
    </main>
@endsection
