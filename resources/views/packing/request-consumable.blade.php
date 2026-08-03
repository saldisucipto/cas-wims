@extends('layouts.operation')

@section('title', 'Request Consumable - WIMS')

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 sm:p-8">
            <div class="flex flex-col gap-2">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Packing Dashboard</p>
                <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Request Consumable</h1>
                <p class="text-slate-600">Select one or more consumable items needed before continuing your work.</p>
            </div>

            <form id="requestConsumableForm" class="mt-8 space-y-4">
                <input type="hidden" id="employeeName" value="{{ $employeeName }}">

                <div id="requestRows" class="space-y-3">
                    @include('packing.partials.consumable-request-row', [
                        'index' => 0,
                        'items' => $consumableItems,
                    ])
                </div>

                <button id="addItemButton" type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                    + Add Item
                </button>

                <div>
                    <label for="requestNotes" class="mb-1 block text-sm font-semibold text-slate-700">Notes
                        (Optional)</label>
                    <textarea id="requestNotes" rows="3" placeholder="Additional request notes..."
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500/30"></textarea>
                </div>

                <div class="flex flex-col-reverse gap-3 pt-3 sm:flex-row sm:justify-end">
                    <a href="{{ route('packing.dashboard', ['name' => $employeeName]) }}"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500/60">
                        Submit Request
                    </button>
                </div>
            </form>
        </section>

        <template id="rowTemplate">
            @include('packing.partials.consumable-request-row', [
                'index' => '__INDEX__',
                'items' => $consumableItems,
            ])
        </template>
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
            const rowTemplate = $('#rowTemplate').html();
            const requestRows = $('#requestRows');
            const addItemButton = $('#addItemButton');
            const requestForm = $('#requestConsumableForm');
            const waitingUrl = @json(route('packing.waiting-leader-validation', ['name' => $employeeName]));
            const submitUrl = @json(route('packing.request-consumable.submit'));
            const csrfToken = @json(csrf_token());
            let rowIndex = 1;

            function initializeSelect2(context) {
                context.find('.consumable-item-select').select2({
                    placeholder: 'Search consumable item...',
                    allowClear: true,
                });
            }

            function updateRemoveButtons() {
                const totalRows = requestRows.find('.consumable-row').length;
                requestRows.find('.remove-row-button').prop('disabled', totalRows === 1)
                    .toggleClass('cursor-not-allowed opacity-50', totalRows === 1);
            }

            function collectRequestItems() {
                const items = [];
                let hasInvalidRow = false;

                requestRows.find('.consumable-row').each(function() {
                    const item = $(this).find('.consumable-item-select').val();
                    const quantity = Number($(this).find('.consumable-qty').val());

                    if (!item || !quantity || quantity < 1) {
                        hasInvalidRow = true;
                        return false;
                    }

                    items.push({
                        item,
                        quantity,
                    });
                });

                return {
                    items,
                    hasInvalidRow,
                };
            }

            function resolveRfDevice(employeeName) {
                const searchParams = new URLSearchParams(window.location.search);
                const deviceFromUrl = searchParams.get('device');

                if (deviceFromUrl) {
                    return deviceFromUrl;
                }

                const assignmentsRaw = sessionStorage.getItem('wimsRfAssignments');
                const assignments = assignmentsRaw ? JSON.parse(assignmentsRaw) : {};

                return assignments[employeeName] || null;
            }

            initializeSelect2(requestRows);
            updateRemoveButtons();

            addItemButton.on('click', function() {
                const newRowHtml = rowTemplate.replaceAll('__INDEX__', rowIndex);
                const newRow = $(newRowHtml);

                requestRows.append(newRow);
                initializeSelect2(newRow);
                updateRemoveButtons();

                rowIndex += 1;
            });

            requestRows.on('click', '.remove-row-button', function() {
                if (requestRows.find('.consumable-row').length === 1) {
                    return;
                }

                const row = $(this).closest('.consumable-row');
                row.find('.consumable-item-select').select2('destroy');
                row.remove();

                updateRemoveButtons();
            });

            requestForm.on('submit', function(event) {
                event.preventDefault();

                const {
                    items,
                    hasInvalidRow
                } = collectRequestItems();

                if (hasInvalidRow || items.length === 0) {
                    Swal.fire({
                        title: 'Incomplete Request',
                        text: 'Please select consumable item and quantity for each row.',
                        icon: 'warning',
                        confirmButtonColor: '#1d4ed8',
                    });
                    return;
                }

                const payload = {
                    employeeName: $('#employeeName').val(),
                    rfDevice: resolveRfDevice($('#employeeName').val()),
                    notes: $('#requestNotes').val().trim(),
                    items,
                };

                $.ajax({
                    url: submitUrl,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    data: payload,
                }).done(function() {
                    Swal.fire({
                        title: 'Consumable Request Submitted',
                        text: 'Your consumable request has been successfully submitted.',
                        icon: 'success',
                        confirmButtonColor: '#1d4ed8',
                    }).then(function() {
                        window.location.href = waitingUrl;
                    });
                }).fail(function(error) {
                    const message = error.responseJSON && error.responseJSON.message ? error
                        .responseJSON.message :
                        'Unable to submit request. Please try again.';

                    Swal.fire({
                        title: 'Submit Failed',
                        text: message,
                        icon: 'error',
                        confirmButtonColor: '#1d4ed8',
                    });
                });
            });
        });
    </script>
@endpush
