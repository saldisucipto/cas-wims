@extends('layouts.operation')

@section('title', 'Manpower Division Rules - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Manpower Division Rules</h1>
                    <p class="mt-1 text-sm text-slate-600">Aturan minimum shift per division (mis. Outbound minimal 2 shift).</p>
                </div>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <form action="{{ route('administration.master.manpower-division-rules.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <input name="division" placeholder="Division (e.g. Outbound)" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="minimum_shift" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="1">1 Shift</option>
                    <option value="2">2 Shift</option>
                </select>
                <input name="reason" placeholder="Reason (opsional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Division</th>
                            <th class="px-3 py-2">Minimum Shift</th>
                            <th class="px-3 py-2">Reason</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $row->division }}</td>
                                <td class="px-3 py-2">{{ $row->minimum_shift }} Shift</td>
                                <td class="px-3 py-2">{{ $row->reason ?: '-' }}</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.manpower-division-rules.update', $row) }}"
                                            method="POST" class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-4">
                                            @csrf
                                            <input name="division" value="{{ $row->division }}" class="rounded border border-slate-300 px-2 py-1">
                                            <select name="minimum_shift" class="rounded border border-slate-300 px-2 py-1">
                                                <option value="1" @selected($row->minimum_shift === 1)>1 Shift</option>
                                                <option value="2" @selected($row->minimum_shift === 2)>2 Shift</option>
                                            </select>
                                            <input name="reason" value="{{ $row->reason }}" class="rounded border border-slate-300 px-2 py-1">
                                            <button class="rounded bg-blue-700 px-2 py-1 text-white">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.manpower-division-rules.delete', $row) }}"
                                        method="POST" class="mt-2" onsubmit="return confirm('Delete this division rule?');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="wims-empty-state">Belum ada division rule.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
