@extends('layouts.operation')

@section('title', 'WMS Accounts - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">WMS Accounts</h1>
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

            <form action="{{ route('administration.master.wms-accounts') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search username..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Status</option>
                    @foreach (['Available', 'Assigned', 'Disabled'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </form>

            <form action="{{ route('administration.master.wms-accounts.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-6">
                @csrf
                <input name="username" placeholder="Username" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="password" placeholder="Password" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="function" value="Outbound" placeholder="Function" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Available">Tersedia</option>
                    <option value="Assigned">Dipakai</option>
                    <option value="Disabled">Nonaktif</option>
                </select>
                <select name="daily_worker_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No Worker</option>
                    @foreach ($dailyWorkers as $worker)
                        <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Import Excel WMS Account</h2>
                        <p class="mt-1 text-sm text-slate-600">Gunakan template resmi agar urutan kolom sesuai database.</p>
                        <p class="mt-1 text-xs text-slate-500">Header wajib: {{ implode(', ', $importHeaders) }}</p>
                    </div>
                    <a href="{{ route('administration.master.wms-accounts.template') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-white">Download
                        Template</a>
                </div>

                <form action="{{ route('administration.master.wms-accounts.import') }}" method="POST"
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
                            <th class="px-3 py-2">Username</th>
                            <th class="px-3 py-2">Password</th>
                            <th class="px-3 py-2">Function</th>
                            <th class="px-3 py-2">Worker</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->username }}</td>
                                <td class="px-3 py-2">{{ $row->password }}</td>
                                <td class="px-3 py-2">{{ $row->function }}</td>
                                <td class="px-3 py-2">{{ $row->dailyWorker?->name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row->status }}</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.wms-accounts.update', $row) }}"
                                            method="POST" class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-5">
                                            @csrf
                                            <input name="username" value="{{ $row->username }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="password" value="{{ $row->password }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <input name="function" value="{{ $row->function }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <select name="status" class="rounded border border-slate-300 px-2 py-1">
                                                @foreach (['Available', 'Assigned', 'Disabled'] as $status)
                                                    <option value="{{ $status }}" @selected($row->status === $status)>
                                                        {{ $status }}</option>
                                                @endforeach
                                            </select>
                                            <select name="daily_worker_id" class="rounded border border-slate-300 px-2 py-1">
                                                <option value="">No Worker</option>
                                                @foreach ($dailyWorkers as $worker)
                                                    <option value="{{ $worker->id }}" @selected($row->daily_worker_id === $worker->id)>{{ $worker->name }}</option>
                                                @endforeach
                                            </select>
                                            <button
                                                class="rounded bg-blue-700 px-2 py-1 text-white md:col-span-5">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.wms-accounts.delete', $row) }}"
                                        method="POST" class="mt-2"
                                        onsubmit="return confirm('Delete this WMS account?');">
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
