@extends('layouts.operation')

@section('title', 'Master ATK - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Master ATK</h1>
                </div>
                <a href="{{ route('administration.dashboard') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('administration.master.atk') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search ATK..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 md:col-span-2">
                <select name="category" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    <option value="">All Category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900">
                    <option value="">All Status</option>
                    <option value="Active" @selected(($filters['status'] ?? '') === 'Active')>Active</option>
                    <option value="Inactive" @selected(($filters['status'] ?? '') === 'Inactive')>Inactive</option>
                </select>
                <button type="submit"
                    class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800 md:col-span-4">Search</button>
            </form>

            <form action="{{ route('administration.master.atk.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <input name="code" value="{{ old('code') }}" placeholder="Kode ATK" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="name" value="{{ old('name') }}" placeholder="Nama ATK" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="category" value="{{ old('category') }}" placeholder="Kategori" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="unit" value="{{ old('unit') }}" placeholder="Satuan" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="minimum_stock" type="number" min="0" value="{{ old('minimum_stock', 0) }}"
                    placeholder="Minimum Stock" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="current_stock" type="number" min="0" value="{{ old('current_stock', 0) }}"
                    placeholder="Current Stock" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Active" @selected(old('status', 'Active') === 'Active')>Active</option>
                    <option value="Inactive" @selected(old('status') === 'Inactive')>Inactive</option>
                </select>
                <input name="notes" value="{{ old('notes') }}" placeholder="Keterangan"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button type="submit"
                    class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white md:col-span-4">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Kode ATK</th>
                            <th class="px-3 py-2">Nama ATK</th>
                            <th class="px-3 py-2">Kategori</th>
                            <th class="px-3 py-2">Satuan</th>
                            <th class="px-3 py-2">Minimum Stock</th>
                            <th class="px-3 py-2">Current Stock</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Keterangan</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->code }}</td>
                                <td class="px-3 py-2">{{ $row->name }}</td>
                                <td class="px-3 py-2">{{ $row->category }}</td>
                                <td class="px-3 py-2">{{ $row->unit }}</td>
                                <td class="px-3 py-2">{{ $row->minimum_stock }}</td>
                                <td class="px-3 py-2">{{ $row->current_stock }}</td>
                                <td class="px-3 py-2">{{ $row->status }}</td>
                                <td class="px-3 py-2">{{ $row->notes ?: '-' }}</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.atk.update', $row) }}" method="POST"
                                            class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-4">
                                            @csrf
                                            <input name="code" value="{{ $row->code }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="name" value="{{ $row->name }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="category" value="{{ $row->category }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="unit" value="{{ $row->unit }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="minimum_stock" type="number" min="0"
                                                value="{{ $row->minimum_stock }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="current_stock" type="number" min="0"
                                                value="{{ $row->current_stock }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <select name="status" class="rounded border border-slate-300 px-2 py-1">
                                                <option value="Active" @selected($row->status === 'Active')>Active</option>
                                                <option value="Inactive" @selected($row->status === 'Inactive')>Inactive</option>
                                            </select>
                                            <input name="notes" value="{{ $row->notes }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <button type="submit"
                                                class="rounded bg-blue-700 px-2 py-1 text-white md:col-span-4">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.atk.delete', $row) }}" method="POST"
                                        class="mt-2" onsubmit="return confirm('Delete this ATK item?');">
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
