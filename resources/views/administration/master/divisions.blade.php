@extends('layouts.operation')

@section('title', 'Divisions - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Divisions</h1>
                </div>
                <a href="{{ route('administration.dashboard') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            <form action="{{ route('administration.master.divisions.store') }}" method="POST" class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
                @csrf
                <input name="code" placeholder="Code (e.g. INB)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="name" placeholder="Name (e.g. Inbound)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600"><tr><th class="px-3 py-2">Code</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $row->code }}</td>
                                <td class="px-3 py-2">{{ $row->name }}</td>
                                <td class="px-3 py-2">
                                    <form action="{{ route('administration.master.divisions.delete', $row) }}" method="POST" onsubmit="return confirm('Delete this division?');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><div class="wims-empty-state">Belum ada division.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
