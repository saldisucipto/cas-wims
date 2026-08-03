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
                <label for="employee" class="block text-sm font-semibold text-slate-700">Employee</label>
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

            <div id="employeeCard" class="mt-6 hidden rounded-xl border border-slate-200 bg-slate-50 p-5">
                <p class="text-xs uppercase tracking-widest text-slate-500">Selected Employee</p>

                <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-slate-500">Name</dt>
                        <dd id="employeeName" class="font-semibold text-slate-900">-</dd>
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
    </style>
@endpush

@push('scripts')
    <script>
        $(function() {
            const employeeSelect = $('#employee');
            const employeeCard = $('#employeeCard');
            const employeeName = $('#employeeName');
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

            startWorkingButton.prop('disabled', true);

            employeeSelect.on('change', function() {
                const selectedName = $(this).val();

                if (!selectedName) {
                    employeeCard.addClass('hidden');
                    employeeName.text('-');
                    employeeFunction.text('-');
                    employeePosition.text('-');
                    startWorkingButton.prop('disabled', true);
                    return;
                }

                const selectedOption = employeeSelect.find('option:selected');

                employeeName.text(selectedName);
                employeeFunction.text(selectedOption.data('function') || '-');
                employeePosition.text(selectedOption.data('position') || '-');
                employeeCard.removeClass('hidden');
                startWorkingButton.prop('disabled', false);
            });

            startWorkingButton.on('click', function() {
                const selectedName = employeeSelect.val();

                if (!selectedName) {
                    return;
                }

                const dashboardUrl = $(this).data('dashboard-url');
                window.location.href = `${dashboardUrl}?name=${encodeURIComponent(selectedName)}`;
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
