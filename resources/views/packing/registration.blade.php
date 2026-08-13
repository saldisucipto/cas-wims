@extends('layouts.operation')

@section('title', 'Packing Station Registration - WIMS')

@section('content')
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
        <section
            class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 sm:p-10">
            <div class="text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">WIMS</p>
                <h1 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">Packing Station Registration</h1>
                <p class="mt-3 text-slate-600">Select your name to start working.</p>
            </div>

            <div class="mt-8 space-y-5">
                <div>
                    <label for="employee" class="mb-1 block text-sm font-semibold text-slate-700">Employee</label>
                    @if ($employees->isEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            Tidak ada karyawan yang tersedia untuk memulai sesi kerja.
                        </div>
                    @else
                        <select id="employee" class="w-full" data-placeholder="Cari nama atau kode karyawan...">
                            <option value=""></option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->name }}" data-code="{{ $employee->employee_code }}"
                                    data-function="{{ $employee->function }}" data-position="{{ $employee->position }}">
                                    {{ $employee->name }} | {{ $employee->employee_code }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Packing Station</label>
                    @if ($stations->isEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                            Tidak ada meja packing yang tersedia.
                        </div>
                    @else
                        <div id="stationCardGrid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($stations as $station)
                                <button type="button"
                                    class="station-card relative rounded-xl border-2 border-slate-200 bg-white p-4 text-center transition hover:border-blue-400 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60"
                                    data-station-code="{{ $station->code }}" data-station-name="{{ $station->name }}">
                                    <span
                                        class="station-check absolute top-2 right-2 h-5 w-5 items-center justify-center rounded-full bg-blue-700 text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                            class="h-3.5 w-3.5">
                                            <path fill-rule="evenodd"
                                                d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                    <span
                                        class="station-icon mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-lg font-extrabold text-blue-700">
                                        {{ $station->station_number ?? $station->code }}
                                    </span>
                                    <span class="mt-3 block text-sm font-bold text-slate-900">{{ $station->name }}</span>
                                    <span
                                        class="mt-1 flex items-center justify-center gap-1 text-xs font-semibold tracking-wide text-slate-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        {{ $station->code }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div id="employeeCard" class="mt-6 hidden rounded-xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs uppercase tracking-widest text-slate-500">Assignment Preview</p>

                <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-slate-500">Name</dt>
                        <dd id="employeeName" class="font-semibold text-slate-900">-</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Packing Station</dt>
                        <dd id="packingStationName" class="font-semibold text-slate-900">-</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Function</dt>
                        <dd id="employeeFunction" class="font-semibold text-slate-900">-</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Position</dt>
                        <dd id="employeePosition" class="font-semibold text-slate-900">-</dd>
                    </div>
                </dl>
            </div>

            <button id="startWorkingButton" type="button" data-dashboard-url="{{ route('packing.dashboard') }}"
                class="mt-8 inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-6 py-4 text-base font-semibold text-white transition hover:bg-blue-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500/60 disabled:cursor-not-allowed disabled:bg-slate-300">
                Start Working
            </button>
        </section>
    </main>
@endsection

@push('styles')
    <style>
        .select2-container {
            width: 100% !important;
        }

        .select2-container .select2-selection--single {
            height: 48px;
            border-radius: 0.75rem;
            border-color: rgb(203 213 225);
            display: flex;
            align-items: center;
            padding-left: 0.5rem;
        }

        .select2-container .select2-selection__rendered {
            color: rgb(15 23 42) !important;
            line-height: 48px !important;
            padding-right: 2.25rem !important;
        }

        .select2-container .select2-selection__placeholder {
            color: rgb(100 116 139) !important;
        }

        .select2-container .select2-selection__arrow {
            height: 48px !important;
            right: 0.6rem !important;
        }

        .select2-dropdown {
            border-color: rgb(203 213 225);
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .select2-search__field {
            border-color: rgb(203 213 225) !important;
            border-radius: 0.5rem;
        }

        .station-card .station-check {
            display: none;
        }

        .station-card.is-selected {
            border-color: rgb(29 78 216);
            background-color: rgb(239 246 255);
            box-shadow: 0 0 0 3px rgb(59 130 246 / 0.25);
        }

        .station-card.is-selected .station-icon {
            background-color: rgb(29 78 216);
            color: #fff;
        }

        .station-card.is-selected .station-check {
            display: flex;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(function() {
            const employeeSelect = $('#employee');
            const stationCards = $('.station-card');
            const employeeCard = $('#employeeCard');
            const employeeName = $('#employeeName');
            const packingStationName = $('#packingStationName');
            const employeeFunction = $('#employeeFunction');
            const employeePosition = $('#employeePosition');
            const startWorkingButton = $('#startWorkingButton');

            if (!employeeSelect.length) {
                startWorkingButton.prop('disabled', true);
                return;
            }

            function employeeMatcher(params, data) {
                const term = $.trim(params.term || '').toLowerCase();
                if (term === '') {
                    return data;
                }

                const element = data.element;
                if (!element) {
                    return null;
                }

                const option = $(element);
                const name = (option.val() || '').toLowerCase();
                const code = (option.data('code') || '').toLowerCase();

                if (name.includes(term) || code.includes(term)) {
                    return data;
                }

                return null;
            }

            function employeeTemplate(data) {
                if (!data.id) {
                    return data.text;
                }

                const option = $(data.element);
                const name = option.val() || '';
                const code = option.data('code') || '-';
                const fn = option.data('function') || '-';
                const position = option.data('position') || '-';

                return $(
                    '<div class="py-1">' +
                    '<div class="font-semibold text-slate-900">' + name + '</div>' +
                    '<div class="text-xs text-slate-600">' + fn + ' • ' + position + '</div>' +
                    '<div class="text-xs text-slate-500">' + code + '</div>' +
                    '</div>'
                );
            }

            function employeeSelectionTemplate(data) {
                if (!data.id) {
                    return data.text;
                }

                const option = $(data.element);
                return option.val() || data.text;
            }

            employeeSelect.select2({
                placeholder: employeeSelect.data('placeholder'),
                allowClear: true,
                matcher: employeeMatcher,
                templateResult: employeeTemplate,
                templateSelection: employeeSelectionTemplate,
                escapeMarkup: function(markup) {
                    return markup;
                },
            });

            let selectedStation = null;

            stationCards.on('click', function() {
                const card = $(this);
                const code = card.data('station-code');

                if (selectedStation === code) {
                    selectedStation = null;
                    stationCards.removeClass('is-selected');
                } else {
                    selectedStation = code;
                    stationCards.removeClass('is-selected');
                    card.addClass('is-selected');
                }

                refreshAssignment();
            });

            function refreshAssignment() {
                const selectedName = employeeSelect.val();

                if (!selectedName) {
                    employeeCard.addClass('hidden');
                    employeeName.text('-');
                    packingStationName.text('-');
                    employeeFunction.text('-');
                    employeePosition.text('-');
                    startWorkingButton.prop('disabled', true);
                    return;
                }

                const selectedOption = employeeSelect.find('option:selected');
                const stationName = selectedStation
                    ? stationCards.filter('[data-station-code="' + selectedStation + '"]').data('station-name')
                    : null;

                employeeName.text(selectedName);
                packingStationName.text(stationName || '-');
                employeeFunction.text(selectedOption.data('function') || '-');
                employeePosition.text(selectedOption.data('position') || '-');
                employeeCard.removeClass('hidden');
                startWorkingButton.prop('disabled', !selectedStation);
            }

            employeeSelect.on('change', refreshAssignment);

            refreshAssignment();

            startWorkingButton.on('click', function() {
                const selectedName = employeeSelect.val();

                if (!selectedName || !selectedStation) {
                    return;
                }

                const dashboardUrl = $(this).data('dashboard-url');
                const query = new URLSearchParams({
                    name: selectedName,
                    station: selectedStation,
                });

                window.location.href = `${dashboardUrl}?${query.toString()}`;
            });

            @if (session('error'))
                Swal.fire({
                    title: 'Cannot Start Session',
                    text: @json(session('error')),
                    icon: 'error',
                    confirmButtonColor: '#dc2626',
                });
            @endif

            @if (session('success'))
                Swal.fire({
                    title: 'Success',
                    text: @json(session('success')),
                    icon: 'success',
                    confirmButtonColor: '#2563eb',
                });
            @endif
        });
    </script>
@endpush
