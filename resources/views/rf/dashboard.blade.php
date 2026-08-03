@extends('layouts.operation')

@section('title', 'RF Handheld Dashboard - WIMS')

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">RF Handheld Dashboard</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Welcome, {{ $employeeName }}</h1>

            <div class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Function</p>
                    <p class="font-semibold text-slate-900">Outbound</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">Position</p>
                    <p class="font-semibold text-slate-900">Picker</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-slate-500">RF Device</p>
                    <p class="font-semibold text-slate-900">{{ $deviceName }}</p>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3">
                    <p class="text-slate-500">Status</p>
                    <p class="font-semibold text-green-700">🟢 Device Connected</p>
                </div>
            </div>
        </header>

        <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 xl:col-span-1">
                <h2 class="text-lg font-semibold text-slate-900">WMS RF Account</h2>

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
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 xl:col-span-2">
                <h2 class="text-lg font-semibold text-slate-900">Session Information</h2>

                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-500">Current Shift</dt>
                        <dd class="mt-1 text-base font-semibold text-slate-900">Morning Shift</dd>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-500">Task Queue</dt>
                        <dd class="mt-1 text-base font-semibold text-slate-900">12 Picking Tasks</dd>
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
                        <dt class="text-sm text-slate-500">Session Duration</dt>
                        <dd id="sessionDuration" class="mt-1 text-base font-semibold text-slate-900">00:00:00</dd>
                    </div>
                </dl>
            </article>
        </section>

        <div class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <a href="{{ route('rf.registration') }}"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-6 py-4 text-base font-semibold text-slate-700 transition hover:bg-slate-100">
                Reassign Device
            </a>
            <button id="finishRfSessionButton" type="button"
                class="inline-flex items-center justify-center rounded-xl bg-red-600 px-6 py-4 text-base font-semibold text-white transition hover:bg-red-700 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-red-500/60">
                Finish RF Session
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

            function updateSessionInfo() {
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

                $('#sessionDuration').text(formatDuration(elapsedSeconds));
            }

            updateSessionInfo();
            setInterval(updateSessionInfo, 1000);

            $('#finishRfSessionButton').on('click', function() {
                Swal.fire({
                    title: 'Finish RF Session?',
                    text: 'This will end current RF session and release assigned resources.',
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

                    const finishUrl = new URL(@json(route('rf.dashboard')), window.location.origin);
                    finishUrl.searchParams.set('name', @json($employeeName));
                    finishUrl.searchParams.set('device', @json($deviceName));
                    finishUrl.searchParams.set('finish', '1');
                    window.location.href = finishUrl.toString();
                });
            });
        });
    </script>
@endpush
