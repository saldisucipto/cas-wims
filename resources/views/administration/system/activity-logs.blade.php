@extends('layouts.operation')

@section('title', 'Activity Logs - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">Activity Logs</h1>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
            </div>
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($logs as $log)
                            <tr>
                                <td class="px-3 py-2">{{ $log['time']?->format('d M Y H:i:s') ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $log['type'] }}</td>
                                <td class="px-3 py-2">{{ $log['description'] }}</td>
                        </tr>@empty<tr>
                                <td class="px-3 py-2 text-slate-500" colspan="3">No activity logs.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
@endsection
