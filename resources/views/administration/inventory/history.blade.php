@extends('layouts.operation')

@section('title', 'Stock Transaction History - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">Stock Transaction History</h1>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            <form action="{{ route('administration.inventory.transactions') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search consumable..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <select name="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Types</option>
                    @foreach (['Receiving', 'Usage', 'Adjustment', 'Opname'] as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Date Time</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Consumable</th>
                            <th class="px-3 py-2">Before</th>
                            <th class="px-3 py-2">Change</th>
                            <th class="px-3 py-2">After</th>
                            <th class="px-3 py-2">By</th>
                            <th class="px-3 py-2">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->transaction_at->format('d M Y H:i') }}</td>
                                <td class="px-3 py-2">{{ $row->transaction_type }}</td>
                                <td class="px-3 py-2">{{ $row->consumable?->name }}</td>
                                <td class="px-3 py-2">{{ $row->quantity_before }}</td>
                                <td class="px-3 py-2">{{ $row->quantity_change }}</td>
                                <td class="px-3 py-2">{{ $row->quantity_after }}</td>
                                <td class="px-3 py-2">{{ $row->performer?->username ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row->notes ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
    </main>
@endsection
