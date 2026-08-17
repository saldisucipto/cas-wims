@extends('layouts.operation')

@section('title', 'Create Shift Schedule - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="wims-surface p-6">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Scheduling</p>
                    <h1 class="wims-page-title">Create Monthly Shift Schedule</h1>
                    <p class="wims-page-subtitle">Generate jadwal shift bulanan dari Core Employee aktif.</p>
                </div>
                <a href="{{ route('administration.shift-schedules') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <form action="{{ route('administration.shift-schedules.store') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Bulan</label>
                        <select name="month" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected($m === now()->month)>{{ \Carbon\Carbon::create(now()->year, $m, 1)->translatedFormat('F') }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Tahun</label>
                        <input type="number" name="year" value="{{ now()->year }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Manpower Planning (opsional, sumber requirement)</label>
                    <select name="manpower_planning_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Tanpa Manpower Planning</option>
                        @foreach ($plannings as $planning)
                            <option value="{{ $planning->id }}">{{ $planning->planning_number }} ({{ $planning->planning_date }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                </div>
                <button type="submit" class="wims-btn wims-btn-primary">Generate Schedule</button>
            </form>
        </section>
    </main>
@endsection
