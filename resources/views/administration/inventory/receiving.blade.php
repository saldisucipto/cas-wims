@extends('layouts.operation')

@section('title', 'Consumable Receiving - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">Consumable Receiving</h1>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}</p>
            @endif

            <form action="{{ route('administration.inventory.receiving.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <select name="consumable_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Select Consumable</option>
                    @foreach ($consumables as $consumable)
                        <option value="{{ $consumable->id }}">{{ $consumable->name }} (Stock: {{ $consumable->stock }})
                        </option>
                    @endforeach
                </select>
                <input name="quantity" type="number" min="1" placeholder="Received Qty" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="notes" placeholder="Notes" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Post Receiving</button>
            </form>

            <h2 class="mt-8 text-lg font-semibold text-slate-900">Latest Receiving</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Before</th>
                            <th class="px-3 py-2">Change</th>
                            <th class="px-3 py-2">After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($latestTransactions as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->transaction_at->format('d M Y H:i') }}</td>
                                <td class="px-3 py-2">{{ $row->consumable?->name }}</td>
                                <td class="px-3 py-2">{{ $row->quantity_before }}</td>
                                <td class="px-3 py-2">+{{ $row->quantity_change }}</td>
                                <td class="px-3 py-2">{{ $row->quantity_after }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
