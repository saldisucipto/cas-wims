@extends('layouts.operation')

@section('title', 'Manpower VAS Schedule - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Manpower VAS Schedule</h1>
                    <p class="mt-1 text-sm text-slate-600">Jadwal volume VAS per tanggal (bukan persentase dari Inbound).</p>
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

            <form action="{{ route('administration.master.manpower-vas-schedules.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
                @csrf
                <input type="date" name="schedule_date" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="number" name="volume" min="0" placeholder="VAS Volume (pcs)" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add / Update</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">VAS Volume</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ \Carbon\Carbon::parse($row->schedule_date)->translatedFormat('d F Y') }}</td>
                                <td class="px-3 py-2">{{ number_format($row->volume) }} pcs</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.manpower-vas-schedules.update', $row) }}"
                                            method="POST" class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                                            @csrf
                                            <input type="date" name="schedule_date" value="{{ $row->schedule_date }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input type="number" name="volume" min="0" value="{{ $row->volume }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <button class="rounded bg-blue-700 px-2 py-1 text-white">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.manpower-vas-schedules.delete', $row) }}"
                                        method="POST" class="mt-2"
                                        onsubmit="return confirm('Delete this VAS schedule?');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="wims-empty-state">Belum ada VAS schedule.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
    </main>
@endsection
