@extends('layouts.operation')

@section('title', 'Consumable Master - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Consumable Master</h1>
                </div>
                <a href="{{ route('administration.dashboard') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}</p>
            @endif

            <form action="{{ route('administration.master.consumables') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search consumable..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 md:col-span-2">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    <option value="">All Status</option>
                    <option value="Active" @selected(($filters['status'] ?? '') === 'Active')>Active</option>
                    <option value="Inactive" @selected(($filters['status'] ?? '') === 'Inactive')>Inactive</option>
                </select>
                <button type="submit"
                    class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Search</button>
            </form>

            <form action="{{ route('administration.master.consumables.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-5">
                @csrf
                <input name="name" placeholder="Name" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="unit" placeholder="Unit" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="stock" type="number" min="0" placeholder="Stock" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Active">Aktif</option>
                    <option value="Inactive">Tidak Aktif</option>
                </select>
                <button type="submit"
                    class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Unit</th>
                            <th class="px-3 py-2">Stock</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->name }}</td>
                                <td class="px-3 py-2">{{ $row->unit }}</td>
                                <td class="px-3 py-2">{{ $row->stock }}</td>
                                <td class="px-3 py-2">{{ $row->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.consumables.update', $row) }}"
                                            method="POST" class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-4">
                                            @csrf
                                            <input name="name" value="{{ $row->name }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="unit" value="{{ $row->unit }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="stock" type="number" min="0" value="{{ $row->stock }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <select name="status" class="rounded border border-slate-300 px-2 py-1">
                                                <option value="Active" @selected($row->is_active)>Active</option>
                                                <option value="Inactive" @selected(!$row->is_active)>Inactive</option>
                                            </select>
                                            <button type="submit"
                                                class="rounded bg-blue-700 px-2 py-1 text-white md:col-span-4">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.consumables.delete', $row) }}"
                                        method="POST" class="mt-2" onsubmit="return confirm('Delete this consumable?');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
    </main>
@endsection
