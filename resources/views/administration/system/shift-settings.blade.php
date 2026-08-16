@extends('layouts.operation')

@section('title', 'Shift Settings - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">Shift Settings</h1>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    {{ session('success') }}</div>
            @endif

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
                <div class="md:col-span-2">
                    <button type="submit"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save</button>
                </div>
            </form>
        </section>
    </main>
@endsection
