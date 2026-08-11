<div
    class="atk-request-row grid grid-cols-1 gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-[minmax(0,1.4fr)_180px_auto]">
    <select name="items[{{ $index }}][atk_item_id]"
        class="atk-item-select rounded-xl border border-slate-300 px-4 py-3 text-slate-900" required>
        <option value="">Select ATK item</option>
        @foreach ($atkItems as $atkItem)
            <option value="{{ $atkItem->id }}" @selected((string) old("items.$index.atk_item_id") === (string) $atkItem->id)>
                {{ $atkItem->code }} - {{ $atkItem->name }} ({{ $atkItem->category }})
            </option>
        @endforeach
    </select>

    <input name="items[{{ $index }}][quantity]" type="number" min="1" required
        value="{{ old("items.$index.quantity", 1) }}" placeholder="Quantity"
        class="atk-qty rounded-xl border border-slate-300 px-4 py-3 text-slate-900">

    <button type="button"
        class="remove-row-button inline-flex items-center justify-center rounded-xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 transition hover:bg-red-50">
        Remove
    </button>
</div>
