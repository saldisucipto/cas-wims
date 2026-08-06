@extends('layouts.operation')

@section('title', 'Daily Workers - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Daily Workers</h1>
                </div>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">Import gagal.</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('administration.master.daily-workers') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search code/name..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Status</option>
                    <option value="Active" @selected(($filters['status'] ?? '') === 'Active')>Active</option>
                    <option value="Inactive" @selected(($filters['status'] ?? '') === 'Inactive')>Inactive</option>
                </select>
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </form>

            <form action="{{ route('administration.master.daily-workers.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-6">
                @csrf
                <input name="employee_code" placeholder="Employee Code" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="name" placeholder="Full Name" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <input name="function" value="Outbound" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="position" value="Packer" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Active">Aktif</option>
                    <option value="Inactive">Tidak Aktif</option>
                </select>
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white md:col-span-6">Add
                    Worker</button>
            </form>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Import Excel Karyawan</h2>
                        <p class="mt-1 text-sm text-slate-600">Gunakan template resmi agar urutan kolom sesuai database.</p>
                        <p class="mt-1 text-xs text-slate-500">Header wajib: {{ implode(', ', $importHeaders) }}</p>
                    </div>
                    <a href="{{ route('administration.master.daily-workers.template') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">Download
                        Template</a>
                </div>

                <form action="{{ route('administration.master.daily-workers.import') }}" method="POST"
                    enctype="multipart/form-data" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv" required
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
                    <button type="submit"
                        class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Import
                        Data</button>
                </form>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Employee Code</th>
                            <th class="px-3 py-2">Full Name</th>
                            <th class="px-3 py-2">Function</th>
                            <th class="px-3 py-2">Position</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->employee_code }}</td>
                                <td class="px-3 py-2">{{ $row->name }}</td>
                                <td class="px-3 py-2">{{ $row->function }}</td>
                                <td class="px-3 py-2">{{ $row->position }}</td>
                                <td class="px-3 py-2">{{ $row->status }}</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.daily-workers.update', $row) }}"
                                            method="POST" class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                                            @csrf
                                            <input name="employee_code" value="{{ $row->employee_code }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="name" value="{{ $row->name }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="function" value="{{ $row->function }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="position" value="{{ $row->position }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <select name="status" class="rounded border border-slate-300 px-2 py-1">
                                                <option value="Active" @selected($row->status === 'Active')>Active</option>
                                                <option value="Inactive" @selected($row->status === 'Inactive')>Inactive</option>
                                            </select>
                                            <button class="rounded bg-blue-700 px-2 py-1 text-white">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.daily-workers.delete', $row) }}"
                                        method="POST" class="mt-2"
                                        onsubmit="return confirm('Delete this daily worker?');">
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
