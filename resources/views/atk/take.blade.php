@extends('layouts.operation')

@section('title', 'Pengambilan ATK - WIMS')

@section('content')
    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-300/40 sm:p-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-700">Transactions</p>
                    <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Permintaan ATK</h1>
                    <p class="text-slate-600">Ambil item ATK langsung, tanpa perlu akun. Isi nama pengambil dan jumlah item.
                        Sistem otomatis catat pengambil dan update Kartu Stok ATK.</p>
                </div>
                <a href="{{ $backRoute }}"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                    Back
                </a>
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
                $takeItems = old('items', [['atk_item_id' => '', 'quantity' => 1]]);
            @endphp

            <form action="{{ route('atk.take.store') }}" method="POST" class="mt-8 space-y-4">
                @csrf

                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <input type="text" name="taken_by"
                        value="{{ old('taken_by', auth()->user()?->name ?? '') }}" placeholder="Nama Pengambil" required
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="transaction_date"
                        value="{{ old('transaction_date', now()->toDateString()) }}"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                    <input name="notes" value="{{ old('notes') }}" placeholder="Keterangan"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Item Pengambilan</h2>
                            <p class="text-xs text-slate-500">Satu transaksi bisa ambil beberapa item ATK.</p>
                        </div>
                        <button type="button" id="add-item-row"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Tambah
                            Item</button>
                    </div>

                    <div id="take-items" class="mt-4 space-y-3">
                        @foreach ($takeItems as $index => $item)
                            <div class="take-item-row grid grid-cols-1 gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-[minmax(0,1.4fr)_180px_auto]"
                                data-index="{{ $index }}">
                                <select name="items[{{ $index }}][atk_item_id]"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                                    <option value="">Select ATK Item</option>
                                    @foreach ($atkItems as $atkItem)
                                        <option value="{{ $atkItem->id }}" @selected((string) ($item['atk_item_id'] ?? '') === (string) $atkItem->id)>
                                            {{ $atkItem->code }} - {{ $atkItem->name }} (Stock:
                                            {{ $atkItem->current_stock }})
                                        </option>
                                    @endforeach
                                </select>
                                <input name="items[{{ $index }}][quantity]" type="number" min="1"
                                    placeholder="Qty" required value="{{ $item['quantity'] ?? 1 }}"
                                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <button type="button"
                                    class="remove-item-row rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Simpan
                    Pengambilan</button>
            </form>

            <h2 class="mt-8 text-lg font-semibold text-slate-900">Latest Pengambilan ATK</h2>
            <div class="mt-3 space-y-4">
                @forelse ($latestTakes as $transaction)
                    <section class="rounded-xl border border-slate-200 p-4">
                        <div class="grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Tanggal</p>
                                <p class="mt-1 font-semibold text-slate-900">
                                    {{ $transaction['transaction_at']->format('d M Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Nomor Transaksi
                                </p>
                                <p class="mt-1 font-semibold text-slate-900">
                                    {{ $transaction['transaction_number'] ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Diambil Oleh</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $transaction['taken_by'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Keterangan</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ $transaction['notes'] ?: '-' }}</p>
                            </div>
                        </div>

                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2">Item</th>
                                        <th class="px-3 py-2">Keluar</th>
                                        <th class="px-3 py-2">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach ($transaction['items'] as $row)
                                        <tr>
                                            <td class="px-3 py-2">{{ $row->atkItem?->name }}</td>
                                            <td class="px-3 py-2">-{{ $row->quantity_out }}</td>
                                            <td class="px-3 py-2">{{ $row->balance }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <p class="rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-500">Belum ada transaksi
                        pengambilan ATK.</p>
                @endforelse
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const itemContainer = document.getElementById('take-items');
            const addItemButton = document.getElementById('add-item-row');

            if (!itemContainer || !addItemButton) {
                return;
            }

            const optionMarkup = `{!! str_replace(
                '`',
                '\\`',
                trim(
                    collect($atkItems)->map(function ($atkItem) {
                            $label = $atkItem->code . ' - ' . $atkItem->name . ' (Stock: ' . $atkItem->current_stock . ')';
            
                            return '<option value="' . e((string) $atkItem->id) . '">' . e($label) . '</option>';
                        })->implode(''),
                ),
            ) !!}`;

            const createRow = (index) => {
                const wrapper = document.createElement('div');
                wrapper.className =
                    'take-item-row grid grid-cols-1 gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-[minmax(0,1.4fr)_180px_auto]';
                wrapper.dataset.index = index;
                wrapper.innerHTML = `
                    <select name="items[${index}][atk_item_id]" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                        <option value="">Select ATK Item</option>
                        ${optionMarkup}
                    </select>
                    <input name="items[${index}][quantity]" type="number" min="1" value="1" placeholder="Qty" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button type="button" class="remove-item-row rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                `;

                attachRowBehavior(wrapper);

                return wrapper;
            };

            const attachRowBehavior = (row) => {
                const removeButton = row.querySelector('.remove-item-row');

                if (removeButton) {
                    removeButton.addEventListener('click', () => {
                        if (itemContainer.children.length === 1) {
                            return;
                        }

                        row.remove();
                    });
                }
            };

            Array.from(itemContainer.querySelectorAll('.take-item-row')).forEach(attachRowBehavior);

            addItemButton.addEventListener('click', () => {
                const nextIndex = itemContainer.querySelectorAll('.take-item-row').length;
                itemContainer.appendChild(createRow(nextIndex));
            });
        });
    </script>
@endsection
