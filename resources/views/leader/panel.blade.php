@extends('layouts.operation')

@section('title', 'Outbound Leader Panel - WIMS')

@section('content')
    @php
        $activeWorkers = $activeWorkers ?? [];
        $summary = $summary ?? [];
        $pendingRequests = $pendingRequests ?? [];
        $validatedRequests = $validatedRequests ?? collect();
        $rejectedRequests = $rejectedRequests ?? collect();
    @endphp

    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Leader Panel</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Outbound Leader Panel</h1>
                    <p class="mt-2 text-slate-600">Monitor Daily Workers and Validate Consumable Requests.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('atk.take') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                        Ambil ATK
                    </a>
                    <a href="{{ route('atk.requests') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-800">
                        Permintaan ATK
                    </a>
                    <form action="{{ route('leader.logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <section class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($summary as $summaryCard)
                <x-operation-summary-card :label="$summaryCard['label']" :value="$summaryCard['value']" :subvalue="$summaryCard['subvalue']" />
            @endforeach
        </section>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="mb-4 flex flex-col gap-1">
                <h2 class="text-xl font-semibold text-slate-900">Active Daily Workers</h2>
                <p class="text-sm text-slate-600">{{ count($activeWorkers) }} Employees</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Employee Name</th>
                            <th class="px-4 py-3 font-semibold">Function</th>
                            <th class="px-4 py-3 font-semibold">Division</th>
                            <th class="px-4 py-3 font-semibold">Workstation</th>
                            <th class="px-4 py-3 font-semibold">RF Device</th>
                            <th class="px-4 py-3 font-semibold">Working Since</th>
                            <th class="px-4 py-3 font-semibold">Current Shift</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($activeWorkers as $worker)
                            <tr class="bg-white text-slate-800">
                                <td class="px-4 py-3 font-semibold">{{ $worker['name'] }}</td>
                                <td class="px-4 py-3">{{ $worker['function'] }}</td>
                                <td class="px-4 py-3">{{ $worker['division'] }}</td>
                                <td class="px-4 py-3">{{ $worker['workstation'] }}</td>
                                <td class="px-4 py-3">{{ $worker['rf_device'] }}</td>
                                <td class="px-4 py-3">{{ $worker['since'] }}</td>
                                <td class="px-4 py-3">{{ $worker['shift'] }}</td>
                                <td class="px-4 py-3 font-semibold text-green-700">{{ $worker['status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-slate-900">Pending Consumable Requests</h2>
            </div>

            <div id="pendingRequestCards" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($pendingRequests as $request)
                    <article class="request-card rounded-xl border border-slate-200 bg-slate-50 p-5"
                        data-request-id="{{ $request['id'] }}"
                        data-validate-url="{{ route('leader.requests.validate', $request['id']) }}"
                        data-reject-url="{{ route('leader.requests.reject', $request['id']) }}">
                        <div class="flex flex-col gap-3">
                            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                <p><span class="text-slate-500">Employee Name:</span> <span
                                        class="font-semibold text-slate-900">{{ $request['name'] }}</span></p>
                                <p><span class="text-slate-500">Function:</span> <span
                                        class="font-semibold text-slate-900">{{ $request['function'] }}</span></p>
                                <p><span class="text-slate-500">Division:</span> <span
                                        class="font-semibold text-slate-900">{{ $request['division'] }}</span></p>
                                <p><span class="text-slate-500">RF Device:</span> <span
                                        class="font-semibold text-slate-900">{{ $request['rf_device'] }}</span></p>
                                <p><span class="text-slate-500">Request Time:</span> <span
                                        class="font-semibold text-slate-900">{{ $request['request_time'] }}</span></p>
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-slate-700">Requested Consumables</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                    @foreach ($request['items'] as $item)
                                        <li class="flex items-center justify-between rounded-md bg-white px-3 py-2">
                                            <span>{{ $item['name'] }}</span>
                                            <span class="font-semibold">Qty {{ $item['qty'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <p
                                class="request-status inline-flex w-fit items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">
                                🟡 Waiting Validation
                            </p>

                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button type="button"
                                    class="validate-request-button inline-flex items-center justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-green-500/60">
                                    Validate Request
                                </button>
                                <button type="button"
                                    class="reject-request-button inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-red-500/60">
                                    Reject Request
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
                <h2 class="text-lg font-semibold text-slate-900">Validated Requests</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Request</th>
                                <th class="px-3 py-2">Employee</th>
                                <th class="px-3 py-2">RF Device</th>
                                <th class="px-3 py-2">Validated At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($validatedRequests as $request)
                                <tr>
                                    <td class="px-3 py-2">{{ $request->request_number }}</td>
                                    <td class="px-3 py-2">{{ $request->dailyWorker?->name ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $request->rfDevice?->code ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $request->validated_at?->format('d M Y H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-2 text-slate-500" colspan="4">No validated requests.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
                <h2 class="text-lg font-semibold text-slate-900">Rejected Requests</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-3 py-2">Request</th>
                                <th class="px-3 py-2">Employee</th>
                                <th class="px-3 py-2">RF Device</th>
                                <th class="px-3 py-2">Rejected At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse ($rejectedRequests as $request)
                                <tr>
                                    <td class="px-3 py-2">{{ $request->request_number }}</td>
                                    <td class="px-3 py-2">{{ $request->dailyWorker?->name ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $request->rfDevice?->code ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $request->rejected_at?->format('d M Y H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-2 text-slate-500" colspan="4">No rejected requests.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            function disableRequestActions(card) {
                card.find('.validate-request-button, .reject-request-button').prop('disabled', true)
                    .removeClass('hover:bg-green-700 hover:bg-red-700')
                    .addClass('cursor-not-allowed opacity-60');
            }

            const csrfToken = @json(csrf_token());

            $(document).on('click', '.validate-request-button', function() {
                const card = $(this).closest('.request-card');
                const statusElement = card.find('.request-status');

                Swal.fire({
                    title: 'Validate Consumable Request',
                    text: 'Have you verified that all requested consumables match the physical items brought by the employee?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Validate',
                    cancelButtonText: 'Cancel',
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: card.data('validate-url'),
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    }).done(function(response) {
                        Swal.fire({
                            title: 'Success',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#16a34a',
                        });

                        statusElement.removeClass(
                                'bg-amber-100 text-amber-700 bg-red-100 text-red-700')
                            .addClass('bg-green-100 text-green-700')
                            .text('🟢 Validated');
                        disableRequestActions(card);
                    }).fail(function(error) {
                        const message = error.responseJSON && error.responseJSON.message ?
                            error.responseJSON.message :
                            'Unable to validate request.';

                        Swal.fire({
                            title: 'Validation Failed',
                            text: message,
                            icon: 'error',
                            confirmButtonColor: '#dc2626',
                        });
                    });
                });
            });

            $(document).on('click', '.reject-request-button', function() {
                const card = $(this).closest('.request-card');
                const statusElement = card.find('.request-status');

                Swal.fire({
                    title: 'Reject Consumable Request',
                    text: 'Reason: Consumable request rejected.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Reject',
                    cancelButtonText: 'Cancel',
                }).then(function(result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.ajax({
                        url: card.data('reject-url'),
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    }).done(function(response) {
                        Swal.fire({
                            title: 'Rejected',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#dc2626',
                        });

                        statusElement.removeClass(
                                'bg-amber-100 text-amber-700 bg-green-100 text-green-700')
                            .addClass('bg-red-100 text-red-700')
                            .text('🔴 Rejected');
                        disableRequestActions(card);
                    }).fail(function(error) {
                        const message = error.responseJSON && error.responseJSON.message ?
                            error.responseJSON.message :
                            'Unable to reject request.';

                        Swal.fire({
                            title: 'Rejection Failed',
                            text: message,
                            icon: 'error',
                            confirmButtonColor: '#dc2626',
                        });
                    });
                });
            });
        });
    </script>
@endpush
