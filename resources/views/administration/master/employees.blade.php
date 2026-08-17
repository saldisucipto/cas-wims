@extends('layouts.operation')

@section('title', 'Core Employees - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-900">Core Employees</h1>
                    <p class="mt-1 text-sm text-slate-600">Karyawan tetap yang masuk monthly shift schedule.</p>
                </div>
                <a href="{{ route('administration.dashboard') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <form action="{{ route('administration.master.employees.store') }}" method="POST" class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-4">
                @csrf
                <input name="employee_code" placeholder="Employee Code (EMP-001)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="employee_name" placeholder="Employee Name" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="division_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No Division</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}">{{ $division->name }}</option>
                    @endforeach
                </select>
                <select name="position_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">No Position</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}">{{ $position->name }}</option>
                    @endforeach
                </select>
                <select name="shift_pattern" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="ROTATING">Rotating</option>
                    <option value="FIXED_S1">Fixed S1</option>
                    <option value="FIXED_S2">Fixed S2</option>
                </select>
                <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                </select>
                <input name="employment_start_date" type="date" placeholder="Start Date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="employment_end_date" type="date" placeholder="End Date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Add</button>
            </form>

            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Code</th><th class="px-3 py-2">Name</th><th class="px-3 py-2">Division</th>
                            <th class="px-3 py-2">Position</th><th class="px-3 py-2">Pattern</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-3 py-2 font-semibold">{{ $row->employee_code }}</td>
                                <td class="px-3 py-2">{{ $row->employee_name }}</td>
                                <td class="px-3 py-2">{{ $row->division?->name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row->position?->name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $row->shift_pattern }}</td>
                                <td class="px-3 py-2">{{ $row->status }}</td>
                                <td class="px-3 py-2">
                                    <form action="{{ route('administration.master.employees.delete', $row) }}" method="POST" onsubmit="return confirm('Delete this employee?');">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="wims-empty-state">Belum ada employee.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $rows->links() }}</div>
        </section>
    </main>
@endsection
