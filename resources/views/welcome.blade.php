<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'WIMS') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen text-slate-900 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="wims-elevated-card rounded-2xl border border-slate-200/80 bg-white/90 px-5 py-4 backdrop-blur sm:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <span class="wims-company-mark" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V8.25L12 4l7 4.25V21M9 21v-5h6v5M8 11h.01M16 11h.01" />
                        </svg>
                    </span>
                    <div>
                        <p class="wims-brand-eyebrow">PT. Cipta Aneka Servis</p>
                        <p class="text-sm font-semibold text-slate-800">Warehouse Information Management System</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 text-sm text-slate-700 sm:grid-cols-3 sm:items-center sm:gap-6">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-slate-500">Tanggal</p>
                        <p id="currentDate" class="font-medium text-slate-900">{{ $now->format('l, d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-widest text-slate-500">Waktu</p>
                        <p id="currentTime" class="font-medium text-slate-900">{{ $now->format('H:i:s') }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-widest text-slate-500">Status Sistem</p>
                        <p
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1 font-medium {{ $quickStatus['class'] }}">
                            <span class="h-2 w-2 rounded-full bg-current animate-pulse"></span>
                            {{ $quickStatus['emoji'] }} {{ $quickStatus['label'] }}
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <main class="mt-6 flex-1 space-y-6">
            <section class="relative overflow-hidden rounded-2xl bg-slate-950 px-5 py-8 shadow-xl shadow-slate-900/10 sm:px-8 sm:py-10">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_85%_15%,rgba(45,212,191,.26),transparent_21rem),radial-gradient(circle_at_70%_100%,rgba(37,99,235,.32),transparent_26rem)]"></div>
                <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_280px] lg:items-end">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.22em] text-teal-300">Warehouse operations</p>
                        <h1 class="mt-3 max-w-2xl text-3xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
                            Sistem Informasi<br>
                            Manajemen Gudang
                        </h1>
                        <p class="mt-4 max-w-2xl leading-relaxed text-slate-300">
                            Pusat operasional digital PT. Cipta Aneka Servis untuk mengelola aktivitas inbound, inventaris, outbound, consumable, dan perangkat RF.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur-sm">
                        <p class="text-xs font-bold uppercase tracking-[.16em] text-teal-200">Lokasi operasional</p>
                        <p class="mt-2 text-lg font-semibold text-white">PT. Cipta Aneka Servis</p>
                        <p class="mt-1 text-sm leading-relaxed text-slate-300">Warehouse workspace untuk koordinasi tim dan alur kerja harian.</p>
                        <div class="mt-5 flex items-center gap-3 border-t border-white/10 pt-4">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 ring-4 ring-emerald-400/20"></span>
                            <span id="currentShift" class="text-sm font-semibold text-emerald-200">{{ $currentShift['label'] }}</span>
                            <span class="text-xs font-medium text-slate-300">{{ $currentShift['range'] }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section aria-labelledby="summary-heading" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 id="summary-heading" class="text-lg font-semibold tracking-wide text-slate-900">Ringkasan
                        Operasional</h2>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($summaryCards as $summaryCard)
                        <x-operation-summary-card :label="$summaryCard['label']" :value="$summaryCard['value']" :subvalue="$summaryCard['subvalue']" />
                    @endforeach
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <article class="wims-elevated-card rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-base font-semibold tracking-wide text-slate-900">Today's Overview</h3>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs uppercase tracking-widest text-slate-500">Tanggal</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $overview['today_date'] }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs uppercase tracking-widest text-slate-500">Waktu</p>
                            <p id="overviewCurrentTime" class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $overview['current_time'] }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs uppercase tracking-widest text-slate-500">Total Working Sessions</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $overview['total_sessions'] }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs uppercase tracking-widest text-slate-500">Completed Sessions</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $overview['completed_sessions'] }}
                            </p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs uppercase tracking-widest text-slate-500">Active Sessions</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $overview['active_sessions'] }}</p>
                        </div>
                        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs uppercase tracking-widest text-slate-500">Pending Validations</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ $overview['pending_validations'] }}
                            </p>
                        </div>
                    </div>
                </article>

                <article class="wims-elevated-card rounded-xl border border-slate-200 bg-white p-5">
                    <h3 class="text-base font-semibold tracking-wide text-slate-900">Low Stock Warning</h3>
                    <p class="mt-1 text-sm text-slate-600">Stok kurang dari atau sama dengan minimum
                        {{ $lowStockThreshold }}.</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-2 py-2">Consumable</th>
                                    <th class="px-2 py-2">Current Stock</th>
                                    <th class="px-2 py-2">Minimum Stock</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse ($lowStockItems as $item)
                                    <tr>
                                        <td class="px-2 py-2 font-medium text-slate-900">{{ $item->name }}</td>
                                        <td class="px-2 py-2">
                                            <span
                                                class="inline-flex rounded-full border border-red-300 bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $item->stock }}</span>
                                        </td>
                                        <td class="px-2 py-2">
                                            <span
                                                class="inline-flex rounded-full border border-red-300 bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ $lowStockThreshold }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-2 py-3 text-sm text-slate-500">Tidak ada peringatan
                                            stok rendah.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="wims-elevated-card rounded-xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold tracking-wide text-slate-900">Recent Activities</h3>
                <div class="mt-4 divide-y divide-slate-200">
                    @forelse ($recentActivities as $activity)
                        <article class="grid grid-cols-1 gap-2 py-3 sm:grid-cols-[64px_1fr]">
                            <p class="text-sm font-semibold text-slate-700">{{ $activity['time_label'] }}</p>
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $activity['subject'] }}</p>
                                <p class="text-sm text-slate-600">{{ $activity['action'] }}</p>
                                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $activity['detail'] }}</p>
                            </div>
                        </article>
                    @empty
                        <p class="py-3 text-sm text-slate-500">Belum ada aktivitas terbaru.</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="menu-heading" class="space-y-4">
                <h2 id="menu-heading" class="text-lg font-semibold tracking-wide text-slate-900">Menu Utama</h2>

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($menuCards as $menuCard)
                        <x-operation-menu-card :icon="$menuCard['icon']" :title="$menuCard['title']" :description="$menuCard['description']"
                            :href="$menuCard['href']" />
                    @endforeach
                </div>
            </section>
        </main>

        <footer class="mt-6 rounded-xl border border-slate-200 bg-white/80 px-5 py-4 text-sm text-slate-600 shadow-sm shadow-slate-300/40 backdrop-blur">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="wims-brand-eyebrow">WIMS</p>
                    <p class="mt-1 font-semibold text-slate-800">PT. Cipta Aneka Servis Warehouse</p>
                </div>
                <p class="text-xs text-slate-500">Sistem Informasi Manajemen Gudang · Versi 1.0</p>
            </div>
        </footer>
    </div>

    <script>
        const dateElement = document.getElementById('currentDate');
        const timeElement = document.getElementById('currentTime');
        const shiftElement = document.getElementById('currentShift');

        function resolveShift(hour) {
            if (hour >= 7 && hour < 15) {
                return 'Morning Shift';
            }

            if (hour >= 15 && hour < 23) {
                return 'Afternoon Shift';
            }

            return 'Night Shift';
        }

        function updateDateTime() {
            const now = new Date();

            dateElement.textContent = now.toLocaleDateString('id-ID', {
                weekday: 'long',
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });

            timeElement.textContent = now.toLocaleTimeString('id-ID', {
                hour12: false,
            });

            const overviewTimeElement = document.getElementById('overviewCurrentTime');
            if (overviewTimeElement) {
                overviewTimeElement.textContent = now.toLocaleTimeString('id-ID', {
                    hour12: false,
                });
            }

            shiftElement.textContent = resolveShift(now.getHours());
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
</body>

</html>
