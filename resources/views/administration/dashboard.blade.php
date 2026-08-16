@extends('layouts.operation')

@section('title', 'Administration Dashboard - WIMS')

@section('content')
    @php
        $currentRoute = Route::currentRouteName();
    @endphp

    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <header class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Administration</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">Administration Dashboard</h1>
                    <p class="mt-2 text-slate-600">Control master data, inventory transactions, reports and warehouse system
                        configuration.</p>
                </div>

                <form action="{{ route('administration.logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-300/40">
                <h2 class="px-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Navigation</h2>

                <nav class="mt-3 space-y-1">
                    <a href="{{ route('administration.dashboard') }}"
                        class="sidebar-link flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ $currentRoute === 'administration.dashboard' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                        data-active="{{ $currentRoute === 'administration.dashboard' ? 'true' : 'false' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h12A2.25 2.25 0 0 0 20.25 14.25V3M3.75 9h16.5M8.25 21h7.5" />
                        </svg>
                        Dashboard
                    </a>

                    <div class="sidebar-group" data-group="operations">
                        <button type="button"
                            class="sidebar-group-toggle flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                            data-target="operations-list">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 13.5 10.5 6.75l4.5 4.5 5.25-5.25" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 20.25h16.5" />
                                </svg>
                                Operations
                            </span>
                            <svg class="sidebar-chevron h-4 w-4 transition-transform" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <ul id="operations-list" class="sidebar-group-list ml-4 mt-1 space-y-1 hidden">
                            <li><a href="{{ route('packing.registration') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'packing.registration' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'packing.registration' ? 'true' : 'false' }}">Packing
                                    Station</a></li>
                            <li><a href="{{ route('leader.login') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'leader.login' || $currentRoute === 'leader.panel' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'leader.login' || $currentRoute === 'leader.panel' ? 'true' : 'false' }}">Leader
                                    Panel</a></li>
                        </ul>
                    </div>

                    <div class="sidebar-group" data-group="inventory">
                        <button type="button"
                            class="sidebar-group-toggle flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                            data-target="inventory-list">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M20.25 7.5v10.5a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V7.5m16.5 0-8.25 5.25L3.75 7.5m16.5 0L12 2.25 3.75 7.5" />
                                </svg>
                                Inventory
                            </span>
                            <svg class="sidebar-chevron h-4 w-4 transition-transform" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <ul id="inventory-list" class="sidebar-group-list ml-4 mt-1 space-y-1 hidden">
                            <li><a href="{{ route('administration.inventory.receiving') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.inventory.receiving' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.inventory.receiving' ? 'true' : 'false' }}">Consumable
                                    Receiving</a></li>
                            <li><a href="{{ route('administration.inventory.atk-receiving') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.inventory.atk-receiving' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.inventory.atk-receiving' ? 'true' : 'false' }}">Penerimaan
                                    ATK</a></li>
                            <li><a href="{{ route('administration.inventory.adjustment') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.inventory.adjustment' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.inventory.adjustment' ? 'true' : 'false' }}">Stock
                                    Adjustment</a></li>
                            <li><a href="{{ route('administration.inventory.opname') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.inventory.opname' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.inventory.opname' ? 'true' : 'false' }}">Stock
                                    Opname</a></li>
                            <li><a href="{{ route('administration.inventory.transactions') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.inventory.transactions' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.inventory.transactions' ? 'true' : 'false' }}">Stock
                                    Transaction History</a></li>
                        </ul>
                    </div>

                    <div class="sidebar-group" data-group="master-data">
                        <button type="button"
                            class="sidebar-group-toggle flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                            data-target="master-data-list">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h18M3 12h18m-9 4.5h9" />
                                </svg>
                                Master Data
                            </span>
                            <svg class="sidebar-chevron h-4 w-4 transition-transform" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <ul id="master-data-list" class="sidebar-group-list ml-4 mt-1 space-y-1 hidden">
                            <li><a href="{{ route('administration.master.atk') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.atk' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.atk' ? 'true' : 'false' }}">Master
                                    ATK</a>
                            </li>
                            <li><a href="{{ route('administration.master.consumables') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.consumables' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.consumables' ? 'true' : 'false' }}">Consumables</a>
                            </li>
                            <li><a href="{{ route('administration.master.rf-devices') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.rf-devices' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.rf-devices' ? 'true' : 'false' }}">RF
                                    Devices</a></li>
                            <li><a href="{{ route('administration.master.packing-stations') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.packing-stations' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.packing-stations' ? 'true' : 'false' }}">Packing
                                    Stations</a></li>
                            <li><a href="{{ route('administration.master.daily-workers') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.daily-workers' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.daily-workers' ? 'true' : 'false' }}">Daily
                                    Workers</a></li>
                            <li><a href="{{ route('administration.master.wms-accounts') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.wms-accounts' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.wms-accounts' ? 'true' : 'false' }}">WMS
                                    Accounts</a></li>
                            <li><a href="{{ route('administration.master.manpower-activities') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.manpower-activities' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.manpower-activities' ? 'true' : 'false' }}">Manpower
                                    Activities</a></li>
                            <li><a href="{{ route('administration.master.manpower-vas-schedules') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.manpower-vas-schedules' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.manpower-vas-schedules' ? 'true' : 'false' }}">Manpower
                                    VAS Schedule</a></li>
                            <li><a href="{{ route('administration.master.manpower-device-availabilities') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.manpower-device-availabilities' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.manpower-device-availabilities' ? 'true' : 'false' }}">Device
                                    Availability</a></li>
                            <li><a href="{{ route('administration.master.system-users') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.master.system-users' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.master.system-users' ? 'true' : 'false' }}">System
                                    Users</a></li>
                        </ul>
                    </div>

                    <div class="sidebar-group" data-group="transactions">
                        <button type="button"
                            class="sidebar-group-toggle flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                            data-target="transactions-list">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.25 6.75h12m-12 5.25h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75Zm0 5.25h.008v.008H3.75V12Zm0 5.25h.008v.008H3.75v-.008Z" />
                                </svg>
                                Transactions
                            </span>
                            <svg class="sidebar-chevron h-4 w-4 transition-transform" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <ul id="transactions-list" class="sidebar-group-list ml-4 mt-1 space-y-1 hidden">
                            <li><a href="{{ route('atk.take') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'atk.take' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'atk.take' ? 'true' : 'false' }}">Pengambilan
                                    ATK</a></li>
                            <li><a href="{{ route('atk.requests') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'atk.requests' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'atk.requests' ? 'true' : 'false' }}">Permintaan
                                    ATK</a></li>
                        </ul>
                    </div>

                    <div class="sidebar-group" data-group="reports">
                        <button type="button"
                            class="sidebar-group-toggle flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                            data-target="reports-list">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7.5 3v18m4.5-12v12m4.5-6v6" />
                                </svg>
                                Reports
                            </span>
                            <svg class="sidebar-chevron h-4 w-4 transition-transform" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <ul id="reports-list" class="sidebar-group-list ml-4 mt-1 space-y-1 hidden">
                            <li><a href="{{ route('administration.reports.working-sessions') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.reports.working-sessions' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.reports.working-sessions' ? 'true' : 'false' }}">Working
                                    Session Report</a></li>
                            <li><a href="{{ route('administration.manpower-planning') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.manpower-planning' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.manpower-planning' ? 'true' : 'false' }}">Manpower
                                    Planning</a></li>
                            <li><a href="{{ route('administration.manpower-planning.history') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.manpower-planning.history' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.manpower-planning.history' ? 'true' : 'false' }}">Planning
                                    History</a></li>
                            <li><a href="{{ route('administration.reports.atk-stock-card') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.reports.atk-stock-card' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.reports.atk-stock-card' ? 'true' : 'false' }}">Kartu
                                    Stok ATK</a></li>
                            <li><a href="{{ route('administration.reports.consumable-usage') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.reports.consumable-usage' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.reports.consumable-usage' ? 'true' : 'false' }}">Consumable
                                    Usage Report</a></li>
                            <li><a href="{{ route('administration.reports.inventory') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.reports.inventory' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.reports.inventory' ? 'true' : 'false' }}">Inventory
                                    Report</a></li>
                            <li><a href="{{ route('administration.reports.rf-device-usage') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.reports.rf-device-usage' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.reports.rf-device-usage' ? 'true' : 'false' }}">RF
                                    Device Usage Report</a></li>
                            <li><a href="{{ route('administration.reports.daily-worker-activity') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.reports.daily-worker-activity' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.reports.daily-worker-activity' ? 'true' : 'false' }}">Daily
                                    Worker Activity Report</a></li>
                        </ul>
                    </div>

                    <div class="sidebar-group" data-group="system">
                        <button type="button"
                            class="sidebar-group-toggle flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-medium text-slate-700 hover:bg-slate-100"
                            data-target="system-list">
                            <span class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4.5 12a7.5 7.5 0 1 1 15 0 7.5 7.5 0 0 1-15 0Zm7.5-3v6m3-3H9" />
                                </svg>
                                System
                            </span>
                            <svg class="sidebar-chevron h-4 w-4 transition-transform" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <ul id="system-list" class="sidebar-group-list ml-4 mt-1 space-y-1 hidden">
                            <li><a href="{{ route('administration.system.warehouse-settings') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.system.warehouse-settings' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.system.warehouse-settings' ? 'true' : 'false' }}">Warehouse
                                    Settings</a></li>
                            <li><a href="{{ route('administration.system.shift-settings') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.system.shift-settings' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.system.shift-settings' ? 'true' : 'false' }}">Shift
                                    Settings</a></li>
                            <li><a href="{{ route('administration.system.activity-logs') }}"
                                    class="sidebar-link block rounded-lg px-3 py-2 text-sm {{ $currentRoute === 'administration.system.activity-logs' ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100' }}"
                                    data-active="{{ $currentRoute === 'administration.system.activity-logs' ? 'true' : 'false' }}">Activity
                                    Logs</a></li>
                        </ul>
                    </div>
                </nav>
            </aside>

            <div class="space-y-6">
                @if (session('success'))
                    <p class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {{ session('success') }}</p>
                @endif

                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Quick Summary</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">Consumable Items</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m20.25 7.5-8.25 4.5-8.25-4.5m16.5 0L12 3 3.75 7.5m16.5 0v9l-8.25 4.5-8.25-4.5v-9" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $consumableCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Master consumable catalog</p>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">RF Devices</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10.5 2.25h3A2.25 2.25 0 0 1 15.75 4.5v15A2.25 2.25 0 0 1 13.5 21.75h-3A2.25 2.25 0 0 1 8.25 19.5v-15A2.25 2.25 0 0 1 10.5 2.25Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 18h1.5" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $rfDeviceCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Active handheld units</p>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">Daily Workers</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M18 18.72a8.97 8.97 0 0 0-6-2.22 8.97 8.97 0 0 0-6 2.22m12 0a9 9 0 1 0-12 0m12 0A8.97 8.97 0 0 1 12 21a8.97 8.97 0 0 1-6-2.28" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $dailyWorkerCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Registered worker profiles</p>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">System Users</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $systemUserCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Leader and admin accounts</p>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">Total Master ATK</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7.5 6.75h9m-9 5.25h9m-9 5.25h5.25M5.25 3h13.5A2.25 2.25 0 0 1 21 5.25v13.5A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25A2.25 2.25 0 0 1 5.25 3Z" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $atkItemCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Master ATK catalog</p>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">Total Stock ATK</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 7.5 12 3l9 4.5M3 7.5V16.5L12 21m-9-13.5L12 12m9-4.5V16.5L12 21m0-9v9" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $atkStockTotal }}</p>
                            <p class="mt-1 text-xs text-slate-500">Total stok ATK tersedia</p>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">Pending ATK Requests</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $pendingAtkRequestCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">Menunggu persetujuan admin</p>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-slate-600">Approved Today</p>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <p class="mt-4 text-3xl font-bold text-slate-900">{{ $approvedAtkTodayCount }}</p>
                            <p class="mt-1 text-xs text-slate-500">ATK requests approved today</p>
                        </article>
                    </div>
                </section>

                <section>
                    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Pending ATK Approvals</h2>
                            <p class="text-sm text-slate-600">Approve or reject ATK requests from Leaders and
                                Administrators.</p>
                        </div>
                        <a href="{{ route('atk.requests') }}"
                            class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open
                            Request Page</a>
                    </div>

                    @if ($errors->has('atk_approval'))
                        <p class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $errors->first('atk_approval') }}</p>
                    @endif

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        @forelse ($pendingAtkRequests as $request)
                            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Request
                                            Number</p>
                                        <h3 class="mt-1 text-lg font-semibold text-slate-900">
                                            {{ $request->request_number }}</h3>
                                    </div>
                                    <span
                                        class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                    <p><span class="text-slate-500">Request Date:</span> <span
                                            class="font-semibold text-slate-900">{{ $request->requested_at?->format('d M Y H:i') ?? '-' }}</span>
                                    </p>
                                    <p><span class="text-slate-500">Requested By:</span> <span
                                            class="font-semibold text-slate-900">{{ $request->requester?->name ?? '-' }}</span>
                                    </p>
                                    <p class="sm:col-span-2"><span class="text-slate-500">Notes:</span> <span
                                            class="font-semibold text-slate-900">{{ $request->notes ?: '-' }}</span></p>
                                </div>

                                <div class="mt-4">
                                    <p class="text-sm font-semibold text-slate-700">Requested Items</p>
                                    <ul class="mt-2 space-y-1 text-sm text-slate-700">
                                        @foreach ($request->items as $item)
                                            <li class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2">
                                                <span>{{ $item->atkItem?->name ?? '-' }}</span>
                                                <span class="font-semibold">Qty {{ $item->quantity }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <form action="{{ route('administration.atk-requests.approve', $request) }}"
                                        method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex w-full items-center justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-green-700">Approve</button>
                                    </form>

                                    <form action="{{ route('administration.atk-requests.reject', $request) }}"
                                        method="POST" class="space-y-2">
                                        @csrf
                                        <input name="rejection_notes" placeholder="Rejection notes" required
                                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                        <button type="submit"
                                            class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">Reject</button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <div
                                class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm shadow-slate-300/40 xl:col-span-2">
                                No pending ATK requests.
                            </div>
                        @endforelse
                    </div>
                </section>

                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Master Data</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 xl:col-span-1">
                            <div class="flex items-start justify-between">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M7.5 6.75h9m-9 5.25h9m-9 5.25h5.25M5.25 3h13.5A2.25 2.25 0 0 1 21 5.25v13.5A2.25 2.25 0 0 1 18.75 21H5.25A2.25 2.25 0 0 1 3 18.75V5.25A2.25 2.25 0 0 1 5.25 3Z" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Master ATK</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage office supplies data separately from consumables.
                            </p>
                            <a href="{{ route('administration.master.atk') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 xl:col-span-1">
                            <div class="flex items-start justify-between">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m20.25 7.5-8.25 4.5-8.25-4.5m16.5 0L12 3 3.75 7.5m16.5 0v9l-8.25 4.5-8.25-4.5v-9" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Consumable Master</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage warehouse consumable items.</p>
                            <a href="{{ route('administration.master.consumables') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 xl:col-span-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.5 2.25h3A2.25 2.25 0 0 1 15.75 4.5v15A2.25 2.25 0 0 1 13.5 21.75h-3A2.25 2.25 0 0 1 8.25 19.5v-15A2.25 2.25 0 0 1 10.5 2.25Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 18h1.5" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">RF Device Master</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage handheld RF devices.</p>
                            <a href="{{ route('administration.master.rf-devices') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 xl:col-span-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M18 18.72a8.97 8.97 0 0 0-6-2.22 8.97 8.97 0 0 0-6 2.22m12 0a9 9 0 1 0-12 0m12 0A8.97 8.97 0 0 1 12 21a8.97 8.97 0 0 1-6-2.28" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Daily Workers</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage Daily Worker information.</p>
                            <a href="{{ route('administration.master.daily-workers') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 xl:col-span-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">System Users</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage Leader and Administrator accounts.</p>
                            <a href="{{ route('administration.master.system-users') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 xl:col-span-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3.75 6.75h16.5m-16.5 5.25h16.5m-16.5 5.25h16.5" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Packing Stations</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage Packing Station information.</p>
                            <a href="{{ route('administration.master.packing-stations') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40 xl:col-span-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.5 10.5V6.75A2.25 2.25 0 0 0 14.25 4.5h-6A2.25 2.25 0 0 0 6 6.75V10.5m10.5 0h.75A2.25 2.25 0 0 1 19.5 12.75v4.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 17.25v-4.5A2.25 2.25 0 0 1 6.75 10.5H7.5" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">WMS Accounts</h3>
                            <p class="mt-1 text-sm text-slate-600">Manage WMS usernames and passwords used by operators.
                            </p>
                            <a href="{{ route('administration.master.wms-accounts') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                    </div>
                </section>

                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Inventory</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Penerimaan ATK</h3>
                            <p class="mt-1 text-sm text-slate-600">Receive new office supply stock.</p>
                            <a href="{{ route('administration.inventory.atk-receiving') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Consumable Receiving</h3>
                            <p class="mt-1 text-sm text-slate-600">Receive new consumable stock.</p>
                            <a href="{{ route('administration.inventory.receiving') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h18M6 12h12m-9 4.5h6" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Stock Adjustment</h3>
                            <p class="mt-1 text-sm text-slate-600">Increase or decrease stock manually.</p>
                            <a href="{{ route('administration.inventory.adjustment') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v6h6m10.5 12v-6h-6" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20.25 9A8.25 8.25 0 0 0 5.24 5.24L3.75 6.75M3.75 15A8.25 8.25 0 0 0 18.76 18.76l1.49-1.51" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Stock Opname</h3>
                            <p class="mt-1 text-sm text-slate-600">Perform physical stock counting.</p>
                            <a href="{{ route('administration.inventory.opname') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="h-7 w-7 text-blue-700">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A3.375 3.375 0 0 0 11.25 11.625V14.25m8.25 0v4.125A2.625 2.625 0 0 1 16.875 21h-9.75A2.625 2.625 0 0 1 4.5 18.375V14.25m15 0h-15" />
                            </svg>
                            <h3 class="mt-4 text-base font-semibold text-slate-900">Stock Transaction History</h3>
                            <p class="mt-1 text-sm text-slate-600">View all inventory movement history.</p>
                            <a href="{{ route('administration.inventory.transactions') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                    </div>
                </section>

                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Transactions</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Pengambilan ATK</h3>
                            <p class="mt-1 text-sm text-slate-600">Take ATK directly and auto-record who takes each item.
                            </p>
                            <a href="{{ route('atk.take') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>

                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Permintaan ATK</h3>
                            <p class="mt-1 text-sm text-slate-600">Create ATK requests and monitor own request history.</p>
                            <a href="{{ route('atk.requests') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                    </div>
                </section>

                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">Reports</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Working Session Report</h3><a
                                href="{{ route('administration.reports.working-sessions') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Manpower Planning</h3><a
                                href="{{ route('administration.manpower-planning') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Kartu Stok ATK</h3><a
                                href="{{ route('administration.reports.atk-stock-card') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Consumable Usage Report</h3><a
                                href="{{ route('administration.reports.consumable-usage') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Inventory Report</h3><a
                                href="{{ route('administration.reports.inventory') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">RF Device Usage Report</h3><a
                                href="{{ route('administration.reports.rf-device-usage') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Daily Worker Activity Report</h3><a
                                href="{{ route('administration.reports.daily-worker-activity') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                    </div>
                </section>

                <section>
                    <h2 class="mb-3 text-lg font-semibold text-slate-900">System Configuration</h2>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Warehouse Settings</h3><a
                                href="{{ route('administration.system.warehouse-settings') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Shift Settings</h3><a
                                href="{{ route('administration.system.shift-settings') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-300/40">
                            <h3 class="text-base font-semibold text-slate-900">Activity Logs</h3><a
                                href="{{ route('administration.system.activity-logs') }}"
                                class="mt-4 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Open</a>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        $(function() {
            function setGroupState(group, expanded) {
                const list = group.find('.sidebar-group-list');
                const chevron = group.find('.sidebar-chevron');

                if (expanded) {
                    list.removeClass('hidden');
                    chevron.addClass('rotate-180');
                    return;
                }

                list.addClass('hidden');
                chevron.removeClass('rotate-180');
            }

            $('.sidebar-group').each(function() {
                const hasActiveItem = $(this).find('.sidebar-link[data-active="true"]').length > 0;
                setGroupState($(this), hasActiveItem);
            });

            $('.sidebar-group-toggle').on('click', function() {
                const group = $(this).closest('.sidebar-group');
                const isHidden = group.find('.sidebar-group-list').hasClass('hidden');
                setGroupState(group, isHidden);
            });

        });
    </script>
@endpush
