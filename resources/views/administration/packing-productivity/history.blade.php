@extends('layouts.operation')

@section('title', 'Import History - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Packing Productivity</p>
                    <h1 class="wims-page-title">Import History</h1>
                    <p class="wims-page-subtitle">Riwayat import data Meson (audit).</p>
                </div>
                <a href="{{ route('administration.packing-productivity') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="wims-table min-w-full text-left text-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>Period</th><th>File</th><th>Total</th><th>Valid</th><th>Imported</th>
                            <th>Duplicate</th><th>Invalid Operator</th><th>Status</th><th>Imported By</th><th>At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batches as $batch)
                            <tr>
                                <td class="font-semibold">{{ $batch->id }}</td>
                                <td>{{ $batch->start_date->format('d M Y') }} - {{ $batch->end_date->format('d M Y') }}</td>
                                <td class="max-w-xs truncate">{{ $batch->file_name }}</td>
                                <td>{{ number_format($batch->total_rows) }}</td>
                                <td>{{ number_format($batch->valid_rows) }}</td>
                                <td>{{ number_format($batch->imported_rows) }}</td>
                                <td>{{ number_format($batch->duplicate_rows) }}</td>
                                <td>{{ number_format($batch->invalid_operator_rows) }}</td>
                                <td><span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $batch->status }}</span></td>
                                <td>{{ $batch->importer?->name ?? '-' }}</td>
                                <td>{{ $batch->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11"><div class="wims-empty-state">Belum ada riwayat import.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $batches->links() }}</div>
        </section>
    </main>
@endsection
