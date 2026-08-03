@props(['index', 'items'])

<div class="consumable-row rounded-xl border border-slate-200 bg-slate-50 p-4" data-row-index="{{ $index }}">
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
        <div class="lg:col-span-7">
            <label for="item_{{ $index }}" class="mb-1 block text-sm font-semibold text-slate-700">Consumable
                Item</label>
            <select id="item_{{ $index }}" class="consumable-item-select w-full"
                data-placeholder="Search consumable item...">
                <option value=""></option>
                @foreach ($items as $item)
                    <option value="{{ $item }}">{{ $item }}</option>
                @endforeach
            </select>
        </div>

        <div class="lg:col-span-3">
            <label for="qty_{{ $index }}"
                class="mb-1 block text-sm font-semibold text-slate-700">Quantity</label>
            <input id="qty_{{ $index }}" type="number" min="1" value="1"
                class="consumable-qty w-full rounded-xl border border-slate-300 px-4 py-3 text-slate-900 focus:border-blue-500 focus:outline-hidden focus:ring-2 focus:ring-blue-500/30">
        </div>

        <div class="lg:col-span-2">
            <button type="button"
                class="remove-row-button inline-flex w-full items-center justify-center rounded-xl border border-red-200 bg-red-50 px-3 py-3 text-sm font-semibold text-red-700 transition hover:bg-red-100">
                Remove
            </button>
        </div>
    </div>
</div>
