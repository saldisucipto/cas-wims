@extends('layouts.operation')

@section('title', 'Import Packing Productivity - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Packing Productivity</p>
                    <h1 class="wims-page-title">Import Meson Transactions</h1>
                    <p class="wims-page-subtitle">Pilih periode, unggah Excel Meson, lalu konfirmasi untuk mengganti data periode tersebut.</p>
                </div>
                <a href="{{ route('administration.packing-productivity') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('error'))
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <form action="{{ route('administration.packing-productivity.upload') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Start Date</label>
                        <input type="date" name="start_date" required value="{{ old('start_date') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">End Date</label>
                        <input type="date" name="end_date" required value="{{ old('end_date') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Meson Excel File (.xlsx / .csv)</label>
                    <input type="file" name="file" accept=".xlsx,.csv" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="wims-btn wims-btn-primary">Validate &amp; Preview</button>
            </form>
        </section>
    </main>
@endsection
