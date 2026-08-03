@extends('layouts.operation')

@section('title', 'RF Device Master - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">RF Device Master</h1>
                </div>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}</p>
            @endif

            <form action="{{ route('administration.master.rf-devices') }}" method="GET"
                class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search code..."
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm md:col-span-2">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All Status</option>
                    @foreach (['Available', 'In Use', 'Maintenance'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </form>

            <form action="{{ route('administration.master.rf-devices.store') }}" method="POST"
                class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <input name="code" placeholder="RF Code" required
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="Available">Tersedia</option>
                    <option value="In Use">Sedang Digunakan</option>
                    <option value="Maintenance">Dalam Perawatan</option>
                </select>
                <select name="wms_account_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No WMS Account</option>
                    @foreach ($wmsAccounts as $account)
                        <option value="{{ $account->id }}">{{ $account->username }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">WMS Account</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-3 py-2">{{ $row->code }}</td>
                                <td class="px-3 py-2">{{ $row->status }}</td>
                                <td class="px-3 py-2">{{ $row->wmsAccount?->username ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <details>
                                        <summary class="cursor-pointer text-blue-700">Edit</summary>
                                        <form action="{{ route('administration.master.rf-devices.update', $row) }}"
                                            method="POST" class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-4">
                                            @csrf
                                            <input name="code" value="{{ $row->code }}"
                                                class="rounded border border-slate-300 px-2 py-1">
                                            <select name="status" class="rounded border border-slate-300 px-2 py-1">
                                                @foreach (['Available', 'In Use', 'Maintenance'] as $status)
                                                    <option value="{{ $status }}" @selected($row->status === $status)>
                                                        {{ $status }}</option>
                                                @endforeach
                                            </select>
                                            <select name="wms_account_id" class="rounded border border-slate-300 px-2 py-1">
                                                <option value="">No WMS Account</option>
                                                @foreach ($wmsAccounts as $account)
                                                    <option value="{{ $account->id }}" @selected($row->wms_account_id === $account->id)>
                                                        {{ $account->username }}</option>
                                                @endforeach
                                            </select>
                                            <button class="rounded bg-blue-700 px-2 py-1 text-white">Save</button>
                                        </form>
                                    </details>
                                    <form action="{{ route('administration.master.rf-devices.delete', $row) }}"
                                        method="POST" class="mt-2" onsubmit="return confirm('Delete this RF device?');">
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
