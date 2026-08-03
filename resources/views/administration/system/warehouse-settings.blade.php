@extends('layouts.operation')

@section('title', 'Warehouse Settings - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">Warehouse Settings</h1>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    {{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('administration.system.warehouse-settings.save') }}"
                class="mt-5 grid grid-cols-1 gap-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700">Warehouse Name</label>
                    <input type="text" name="warehouse_name"
                        value="{{ old('warehouse_name', $settings['warehouse_name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Warehouse Code</label>
                    <input type="text" name="warehouse_code"
                        value="{{ old('warehouse_code', $settings['warehouse_code'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Warehouse Location</label>
                    <input type="text" name="warehouse_location"
                        value="{{ old('warehouse_location', $settings['warehouse_location'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <button type="submit"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save</button>
                </div>
            </form>
        </section>
    </main>
@endsection
