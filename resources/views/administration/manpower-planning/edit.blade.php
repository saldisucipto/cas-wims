@extends('layouts.operation')

@section('title', 'Edit Manpower Planning - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Edit Planning</p>
                    <h1 class="wims-page-title">{{ $planning->planning_number }}</h1>
                    <p class="wims-page-subtitle">Ubah volume/tanggal lalu simpan untuk recalculate.</p>
                    <p class="wims-breadcrumb">Administration / Reports / Edit Manpower Planning</p>
                </div>
                <a href="{{ route('administration.manpower-planning.show', $planning) }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <form action="{{ route('administration.manpower-planning.update', $planning) }}" method="POST"
                class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="inbound_volume" class="mb-1 block text-sm font-semibold text-slate-700">Inbound Volume (pcs)</label>
                    <input id="inbound_volume" name="inbound_volume" type="number" min="0" value="{{ old('inbound_volume', $planning->inbound_volume) }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label for="outbound_volume" class="mb-1 block text-sm font-semibold text-slate-700">Outbound Volume (order)</label>
                    <input id="outbound_volume" name="outbound_volume" type="number" min="0" value="{{ old('outbound_volume', $planning->outbound_volume) }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label for="planning_date" class="mb-1 block text-sm font-semibold text-slate-700">Planning Date</label>
                    <input id="planning_date" name="planning_date" type="date" value="{{ old('planning_date', $planning->planning_date) }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label for="status" class="mb-1 block text-sm font-semibold text-slate-700">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach (['DRAFT', 'CALCULATED', 'FINAL'] as $status)
                            <option value="{{ $status }}" @selected($planning->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="notes" class="mb-1 block text-sm font-semibold text-slate-700">Notes</label>
                    <textarea id="notes" name="notes" rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $planning->notes) }}</textarea>
                </div>

                <p class="text-xs text-slate-500">
                    Effective Working Hours: <span class="font-semibold">{{ $config['effective_hours'] }} jam/shift</span>.
                    Menyimpan akan melakukan recalculate ulang dan mengganti snapshot hasil.
                </p>

                <div class="flex gap-2">
                    <button type="submit" class="wims-btn wims-btn-primary">Recalculate &amp; Save</button>
                    <a href="{{ route('administration.manpower-planning.show', $planning) }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Cancel</a>
                </div>
            </form>
        </section>
    </main>
@endsection
