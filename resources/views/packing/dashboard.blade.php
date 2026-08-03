@extends('layouts.operation')

@section('title', 'Packing Dashboard - WIMS')

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Packing Dashboard</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Welcome, <span
                    id="workerName">{{ $employeeName }}</span></h1>

            <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Function</p>
                    <p class="font-semibold text-slate-900">Outbound</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Position</p>
                    <p class="font-semibold text-slate-900">Packer</p>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                    <p class="text-slate-500">Status</p>
                    <p class="font-semibold text-green-700">🟢 Working</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Packing Station</p>
                    <p class="font-semibold text-slate-900">{{ $packingStationName }}</p>
                </div>
            </div>
        </header>

        <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 xl:col-span-1">
                <h2 class="text-lg font-semibold text-slate-900">WMS Account</h2>

                <dl class="mt-4 space-y-4 text-sm">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-slate-500">Username</dt>
                        <dd class="mt-1 text-xl font-bold tracking-wide text-blue-700">{{ $wmsUsername }}</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-slate-500">Password</dt>
                        <dd class="mt-1 text-xl font-bold tracking-wide text-slate-900">{{ $wmsPassword }}</dd>
                    </div>
                </dl>

                <a href="{{ route('packing.request-consumable', ['name' => $employeeName]) }}"
                    class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500/60">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"
                        aria-hidden="true">
                        <path
                            d="M12 2.25a.75.75 0 0 1 .75.75v1.387a4.502 4.502 0 0 1 3.862 3.862H18a.75.75 0 0 1 0 1.5h-1.387a4.502 4.502 0 0 1-3.862 3.862V15a.75.75 0 0 1-1.5 0v-1.387a4.502 4.502 0 0 1-3.862-3.862H6a.75.75 0 0 1 0-1.5h1.387a4.502 4.502 0 0 1 3.862-3.862V3a.75.75 0 0 1 .75-.75ZM12 6a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
                        <path
                            d="M5.25 14.25a.75.75 0 0 1 .75.75v2.19l.72-.24a.75.75 0 0 1 .474 1.422l-1.68.56a.75.75 0 0 1-.987-.712V15a.75.75 0 0 1 .75-.75Zm13.5 0a.75.75 0 0 1 .75.75v3.24a.75.75 0 0 1-.987.712l-1.68-.56a.75.75 0 0 1 .474-1.422l.72.24V15a.75.75 0 0 1 .75-.75Z" />
                    </svg>
                    Request Consumable
                </a>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 xl:col-span-2">
                <h2 class="text-lg font-semibold text-slate-900">Working Information</h2>

                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-500">Current Shift</dt>
                        <dd class="mt-1 text-base font-semibold text-slate-900">Morning Shift</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-500">Working Hours</dt>
                        <dd class="mt-1 text-base font-semibold text-slate-900">07:00 - 15:00</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-500">Current Date</dt>
                        <dd id="currentDate" class="mt-1 text-base font-semibold text-slate-900"></dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-500">Current Time</dt>
                        <dd id="currentTime" class="mt-1 text-base font-semibold text-slate-900"></dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:col-span-2">
                        <dt class="text-sm text-slate-500">Working Duration</dt>
                        <dd id="workingDuration" class="mt-1 text-base font-semibold text-slate-900">00:00:00</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 sm:col-span-2">
                        <dt class="text-sm text-slate-500">Consumable Request Status</dt>
                        <dd class="mt-1 text-base font-semibold text-slate-900">{{ $latestRequestStatus }}</dd>
                    </div>
                </dl>
            </article>
        </section>

        <div class="mt-8">
            <button id="finishWorkingButton" type="button"
                class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-6 py-4 text-base font-semibold text-white transition hover:bg-red-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-red-500/60">
                Finish Working
            </button>
        </div>

    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            const startTime = new Date(@json($sessionStartedAt ?? now()->toIso8601String()));

            function formatDuration(totalSeconds) {
                const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
                const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
                const seconds = String(totalSeconds % 60).padStart(2, '0');

                return `${hours}:${minutes}:${seconds}`;
            }

            function updateWorkingInfo() {
                const now = new Date();
                const elapsedSeconds = Math.max(0, Math.floor((now - startTime) / 1000));

                $('#currentDate').text(now.toLocaleDateString('en-GB', {
                    weekday: 'long',
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }));

                $('#currentTime').text(now.toLocaleTimeString('en-GB', {
                    hour12: false,
                }));

                $('#workingDuration').text(formatDuration(elapsedSeconds));
            }

            updateWorkingInfo();
            setInterval(updateWorkingInfo, 1000);

            $('#finishWorkingButton').on('click', function() {
                Swal.fire({
                    title: 'Finish Working?',
                    text: 'This will end current working session and release assigned resources.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, Finish',
                    cancelButtonText: 'Cancel',
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const finishUrl = new URL(@json(route('packing.dashboard')), window.location.origin);
                    finishUrl.searchParams.set('name', @json($employeeName));
                    finishUrl.searchParams.set('finish', '1');
                    window.location.href = finishUrl.toString();
                });
            });
        });
    </script>
@endpush
