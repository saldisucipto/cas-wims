@extends('layouts.operation')

@section('title', 'Waiting Leader Validation - WIMS')

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-5xl items-center px-4 py-6 sm:px-6 lg:px-8">
        <section
            class="w-full rounded-xl border border-slate-200 bg-white p-6 text-center shadow-sm shadow-slate-300/40 sm:p-8">
            <div class="mx-auto h-14 w-14 animate-spin rounded-full border-4 border-slate-200 border-t-blue-700"></div>

            <h1 class="mt-6 text-2xl font-bold text-slate-900 sm:text-3xl">Consumable Request Submitted</h1>

            <div class="mt-6 overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Consumable Item</th>
                            <th class="px-4 py-3 font-semibold">Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-slate-800">
                        @forelse ($requestItems as $item)
                            <tr>
                                <td class="px-4 py-3">{{ $item->consumable?->name ?? '-' }}</td>
                                <td class="px-4 py-3 font-semibold">{{ $item->quantity }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-4 py-3 text-slate-500" colspan="2">No pending consumable request found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mx-auto mt-6 max-w-3xl text-sm leading-relaxed text-slate-600 sm:text-base">
                Please collect the consumables you requested from the Consumable Area.<br>
                After collecting them, bring the consumables to the Outbound Leader desk for validation.<br>
                Do NOT use the consumables until your request has been validated.<br>
                This page will remain open until validation is completed.
            </p>

            <div class="mx-auto mt-6 w-full max-w-3xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-left">
                <p class="font-semibold text-amber-800">Important</p>
                <p class="mt-1 text-sm text-amber-700">Do not close this page.</p>
                <p class="text-sm text-amber-700">Wait until the Outbound Leader validates your consumable request.</p>
            </div>

            <p class="mt-6 text-base font-semibold text-blue-700">Waiting for Leader Validation...</p>

            <button type="button" disabled
                class="mt-6 inline-flex w-full max-w-3xl cursor-not-allowed items-center justify-center rounded-xl bg-slate-300 px-6 py-4 text-base font-semibold text-slate-600">
                Waiting for Validation
            </button>
        </section>
    </main>
@endsection
