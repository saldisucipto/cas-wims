@extends('layouts.operation')

@section('title', 'Consumable Receiving - WIMS')

@section('content')
    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900">Consumable Receiving</h1>
                <a href="{{ route('administration.dashboard') }}"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Back</a>
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

            @php
                $receivingItems = old('items', [['sku_barcode' => '', 'consumable_id' => '', 'quantity' => 1]]);
            @endphp

            <form action="{{ route('administration.inventory.receiving.store') }}" method="POST" class="mt-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <input type="date" name="transaction_date"
                        value="{{ old('transaction_date', now()->toDateString()) }}"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                    <input name="purchase_request_number" value="{{ old('purchase_request_number') }}"
                        placeholder="Nomor Purchase Request" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        required>
                    <input name="received_by_name" value="{{ old('received_by_name') }}" placeholder="Diterima Oleh"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                    <input name="notes" placeholder="Notes" value="{{ old('notes') }}"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Item Receiving</h2>
                            <p class="text-xs text-slate-500">Satu transaksi bisa berisi beberapa item barang.</p>
                        </div>
                        <button type="button" id="add-item-row"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Tambah
                            Item</button>
                    </div>

                    <div id="receiving-items" class="mt-4 space-y-3">
                        @foreach ($receivingItems as $index => $item)
                            <div class="receiving-item-row grid grid-cols-1 gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_180px_auto]"
                                data-index="{{ $index }}">
                                <input name="items[{{ $index }}][sku_barcode]"
                                    value="{{ $item['sku_barcode'] ?? '' }}" placeholder="Scan SKU Barcode"
                                    class="receiving-barcode rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <select name="items[{{ $index }}][consumable_id]"
                                    class="receiving-consumable rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">Select Consumable</option>
                                    @foreach ($consumables as $consumable)
                                        <option value="{{ $consumable->id }}" data-sku="{{ $consumable->sku }}"
                                            data-barcode="{{ $consumable->sku_barcode }}" @selected((string) ($item['consumable_id'] ?? '') === (string) $consumable->id)>
                                            {{ $consumable->sku ? '[' . $consumable->sku . '] ' : '' }}{{ $consumable->name }}
                                            (Stock: {{ $consumable->stock }})
                                        </option>
                                    @endforeach
                                </select>
                                <input name="items[{{ $index }}][quantity]" type="number" min="1"
                                    placeholder="Received Qty" required value="{{ $item['quantity'] ?? 1 }}"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button type="button"
                                    class="remove-item-row rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Post Receiving
                    Transaction</button>
            </form>

            <p class="mt-3 text-xs text-slate-500">Scanner bisa diarahkan ke field SKU Barcode di setiap row. Sistem akan
                memilih consumable otomatis berdasarkan SKU barcode atau SKU.</p>

            <h2 class="mt-8 text-lg font-semibold text-slate-900">Latest Receiving</h2>
            <div class="mt-3 space-y-4">
                @forelse ($latestReceivings as $transaction)
                    <section class="rounded-xl border border-slate-200 p-4">
                        <div class="grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Tanggal</p>
                                <p class="mt-1 font-semibold text-slate-900">
                                    {{ $transaction['transaction_at']->format('d M Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Purchase Request
                                </p>
                                <p class="mt-1 font-semibold text-slate-900">
                                    {{ $transaction['purchase_request_number'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Diterima Oleh
                                </p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $transaction['received_by_name'] ?: '-' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Catatan</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $transaction['notes'] ?: '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Item</th>
                                        <th class="px-3 py-2">Before</th>
                                        <th class="px-3 py-2">Change</th>
                                        <th class="px-3 py-2">After</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach ($transaction['items'] as $row)
                                        <tr>
                                            <td class="px-3 py-2">{{ $row->consumable?->name }}</td>
                                            <td class="px-3 py-2">{{ $row->quantity_before }}</td>
                                            <td class="px-3 py-2">+{{ $row->quantity_change }}</td>
                                            <td class="px-3 py-2">{{ $row->quantity_after }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <p class="rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-500">Belum ada transaksi
                        receiving.</p>
                @endforelse
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const itemContainer = document.getElementById('receiving-items');
            const addItemButton = document.getElementById('add-item-row');

            if (!itemContainer || !addItemButton) {
                return;
            }

            const optionMarkup = `{!! str_replace(
                '`',
                '\\`',
                trim(
                    collect($consumables)->map(function ($consumable) {
                            $label =
                                ($consumable->sku ? '[' . $consumable->sku . '] ' : '') .
                                $consumable->name .
                                ' (Stock: ' .
                                $consumable->stock .
                                ')';
            
                            return '<option value="' .
                                e((string) $consumable->id) .
                                '" data-sku="' .
                                e($consumable->sku ?? '') .
                                '" data-barcode="' .
                                e($consumable->sku_barcode ?? '') .
                                '">' .
                                e($label) .
                                '</option>';
                        })->implode(''),
                ),
            ) !!}`;

            const createRow = (index) => {
                const wrapper = document.createElement('div');
                wrapper.className =
                    'receiving-item-row grid grid-cols-1 gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_180px_auto]';
                wrapper.dataset.index = index;
                wrapper.innerHTML = `
                    <input name="items[${index}][sku_barcode]" placeholder="Scan SKU Barcode" class="receiving-barcode rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <select name="items[${index}][consumable_id]" class="receiving-consumable rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Select Consumable</option>
                        ${optionMarkup}
                    </select>
                    <input name="items[${index}][quantity]" type="number" min="1" value="1" placeholder="Received Qty" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button type="button" class="remove-item-row rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                `;

                attachRowBehavior(wrapper);

                return wrapper;
            };

            const syncSelectionFromBarcode = (row) => {
                const barcodeInput = row.querySelector('.receiving-barcode');
                const consumableSelect = row.querySelector('.receiving-consumable');

                if (!barcodeInput || !consumableSelect) {
                    return;
                }

                const scannedValue = barcodeInput.value.trim();

                if (scannedValue === '') {
                    return;
                }

                const matchedOption = Array.from(consumableSelect.options).find((option) => {
                    return option.dataset.barcode === scannedValue || option.dataset.sku ===
                        scannedValue;
                });

                if (matchedOption) {
                    consumableSelect.value = matchedOption.value;
                }
            };

            const attachRowBehavior = (row) => {
                const barcodeInput = row.querySelector('.receiving-barcode');
                const consumableSelect = row.querySelector('.receiving-consumable');
                const removeButton = row.querySelector('.remove-item-row');

                if (barcodeInput && consumableSelect) {
                    barcodeInput.addEventListener('change', () => syncSelectionFromBarcode(row));
                    barcodeInput.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            syncSelectionFromBarcode(row);
                        }
                    });

                    consumableSelect.addEventListener('change', () => {
                        const selectedOption = consumableSelect.options[consumableSelect.selectedIndex];

                        if (!selectedOption || selectedOption.value === '') {
                            return;
                        }

                        barcodeInput.value = selectedOption.dataset.barcode || selectedOption.dataset
                            .sku || '';
                    });
                }

                if (removeButton) {
                    removeButton.addEventListener('click', () => {
                        if (itemContainer.children.length === 1) {
                            return;
                        }

                        row.remove();
                    });
                }
            };

            Array.from(itemContainer.querySelectorAll('.receiving-item-row')).forEach(attachRowBehavior);

            addItemButton.addEventListener('click', () => {
                const nextIndex = itemContainer.querySelectorAll('.receiving-item-row').length;
                itemContainer.appendChild(createRow(nextIndex));
            });
        });
    </script>
@endsection
