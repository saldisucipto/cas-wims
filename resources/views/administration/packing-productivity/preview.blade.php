@extends('layouts.operation')

@section('title', 'Import Preview - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Packing Productivity</p>
                    <h1 class="wims-page-title">Import Preview</h1>
                </div>
                <a href="{{ route('administration.packing-productivity.import') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <p><span class="font-semibold">Period</span>: {{ \Carbon\Carbon::parse($start_date)->format('d M Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d M Y') }}</p>
                <p><span class="font-semibold">File</span>: {{ $file_name }}</p>
                <p><span class="font-semibold">Transaction Type</span>: {{ implode(', ', $preview['transaction_types']) }}</p>
            </div>

            <div class="mt-5 rounded-xl border border-slate-200 bg-white p-4 text-sm">
                <table class="w-full">
                    <tbody class="divide-y divide-slate-100">
                        <tr><td class="py-1.5 text-slate-600">Total Excel Rows</td><td class="py-1.5 text-right font-semibold">{{ number_format($preview['total_rows']) }}</td></tr>
                        <tr><td class="py-1.5 text-slate-600">Matching Period</td><td class="py-1.5 text-right font-semibold">{{ number_format($preview['matching_period']) }}</td></tr>
                        <tr><td class="py-1.5 text-slate-600">Outside Period</td><td class="py-1.5 text-right font-semibold">{{ number_format($preview['outside_period']) }}</td></tr>
                        @if ($preview['excluded_by_type'] > 0)
                            <tr><td class="py-1.5 text-slate-600">Excluded by Transaction Type</td><td class="py-1.5 text-right font-semibold">{{ number_format($preview['excluded_by_type']) }}</td></tr>
                        @endif
                        @if ($preview['invalid_time'] > 0)
                            <tr><td class="py-1.5 text-red-600">Invalid Transaction Time</td><td class="py-1.5 text-right font-semibold text-red-600">{{ number_format($preview['invalid_time']) }}</td></tr>
                        @endif
                        @if ($preview['duplicate_rows'] > 0)
                            <tr><td class="py-1.5 text-slate-600">Duplicate Transaction ID (skipped)</td><td class="py-1.5 text-right font-semibold">{{ number_format($preview['duplicate_rows']) }}</td></tr>
                        @endif
                        <tr><td class="py-1.5 text-slate-600">Valid Operators</td><td class="py-1.5 text-right font-semibold">{{ number_format($preview['valid_operator_rows']) }}</td></tr>
                        <tr><td class="py-1.5 {{ $preview['invalid_operator_rows'] > 0 ? 'text-red-600' : 'text-slate-600' }}">Invalid Operators</td><td class="py-1.5 text-right font-semibold {{ $preview['invalid_operator_rows'] > 0 ? 'text-red-600' : '' }}">{{ number_format($preview['invalid_operator_rows']) }}</td></tr>
                        <tr class="border-t border-slate-200"><td class="py-2 text-slate-600">Existing Period Data</td><td class="py-2 text-right font-semibold">{{ number_format($preview['existing_count']) }} transactions</td></tr>
                        <tr><td class="py-1.5 text-slate-600">After Import</td><td class="py-1.5 text-right font-bold text-slate-900">{{ number_format($preview['after_count']) }} transactions</td></tr>
                    </tbody>
                </table>
            </div>

            @if (! empty($preview['invalid_operator_values']))
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Invalid operator values (not found in WMS Accounts):</p>
                    <p class="mt-1 font-mono">{{ implode(', ', $preview['invalid_operator_values']) }}</p>
                </div>
            @endif

            <div class="mt-6 flex items-center gap-3">
                <form action="{{ route('administration.packing-productivity.confirm') }}" method="POST">
                    @csrf
                    <button type="submit" class="wims-btn wims-btn-success" onclick="return confirm('Replace existing data for this period and import?');">Confirm Import</button>
                </form>
                <form action="{{ route('administration.packing-productivity.cancel') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</button>
                </form>
            </div>
        </section>
    </main>
@endsection
