@extends('layouts.operation')

@section('title', 'Permintaan ATK - WIMS')

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 sm:p-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Transactions</p>
                    <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Permintaan ATK</h1>
                    <p class="text-slate-600">Buat permintaan satu atau lebih item ATK. Permintaan diproses setelah disetujui
                        Administrator.</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('atk.take') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800">
                        Ambil ATK
                    </a>
                    <a href="{{ $backRoute }}"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                        Back
                    </a>
                </div>
            </div>

            @if (session('success'))
                <p class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}</p>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('atk.requests.store') }}" method="POST" id="atkRequestForm" class="mt-8 space-y-4">
                @csrf

                <div id="requestRows" class="space-y-3">
                    @php
                        $rows = old('items', [['atk_item_id' => '', 'quantity' => 1]]);
                    @endphp
                    @foreach ($rows as $index => $row)
                        @include('atk.partials.request-row', ['index' => $index, 'atkItems' => $atkItems])
                    @endforeach
                </div>

                <button id="addItemButton" type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                    + Add Item
                </button>

                <div>
                    <label for="requestNotes" class="mb-1 block text-sm font-semibold text-slate-700">Notes
                        (Optional)</label>
                    <textarea id="requestNotes" name="notes" rows="3" placeholder="Additional request notes..."
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500/30">{{ old('notes') }}</textarea>
                </div>

                <div class="flex justify-end pt-3">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-800 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-blue-500/60">
                        Submit Request
                    </button>
                </div>
            </form>
        </section>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex flex-col gap-2">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">History</p>
                <h2 class="text-xl font-bold text-slate-900">Request History</h2>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2">Request Number</th>
                            <th class="px-3 py-2">Request Date</th>
                            <th class="px-3 py-2">Items</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Approval Info</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($requestHistory as $row)
                            <tr>
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ $row->request_number }}</td>
                                <td class="px-3 py-2">{{ $row->requested_at?->format('d M Y H:i') ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <ul class="space-y-1 text-slate-700">
                                        @foreach ($row->items as $item)
                                            <li>{{ $item->atkItem?->name ?? '-' }} - Qty {{ $item->quantity }}</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $row->status === 'Approved' ? 'bg-green-100 text-green-700' : ($row->status === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                        {{ $row->status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-slate-700">
                                    @if ($row->status === 'Approved')
                                        Approved by {{ $row->approver?->name ?? '-' }}<br>
                                        {{ $row->approved_at?->format('d M Y H:i') ?? '-' }}
                                    @elseif ($row->status === 'Rejected')
                                        Rejected by {{ $row->rejector?->name ?? '-' }}<br>
                                        {{ $row->rejected_at?->format('d M Y H:i') ?? '-' }}<br>
                                        {{ $row->rejection_notes ?: '-' }}
                                    @else
                                        Waiting approval
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-6 text-center text-slate-500">No ATK request history yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $requestHistory->links() }}</div>
        </section>

        <template id="rowTemplate">
            @include('atk.partials.request-row', ['index' => '__INDEX__', 'atkItems' => $atkItems])
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
    </style>
@endpush

@push('scripts')
    <script>
        $(function() {
            const rowTemplate = $('#rowTemplate').html();
            const requestRows = $('#requestRows');
            const addItemButton = $('#addItemButton');
            let rowIndex = requestRows.find('.atk-request-row').length;

            function initializeSelect2(context) {
                context.find('.atk-item-select').select2({
                    placeholder: 'Search ATK item...',
                    allowClear: true,
                });
            }

            function updateRemoveButtons() {
                const totalRows = requestRows.find('.atk-request-row').length;
                requestRows.find('.remove-row-button').prop('disabled', totalRows === 1)
                    .toggleClass('cursor-not-allowed opacity-50', totalRows === 1);
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
                if (requestRows.find('.atk-request-row').length === 1) {
                    return;
                }

                const row = $(this).closest('.atk-request-row');
                row.find('.atk-item-select').select2('destroy');
                row.remove();
                updateRemoveButtons();
            });
        });
    </script>
@endpush
