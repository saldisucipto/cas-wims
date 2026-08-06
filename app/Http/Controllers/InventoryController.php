<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function receiving(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.inventory.receiving', [
            'consumables' => Consumable::query()->where('is_active', true)->orderBy('name')->get(),
            'latestReceivings' => StockTransaction::query()
                ->with('consumable')
                ->where('transaction_type', 'Receiving')
                ->latest('transaction_at')
                ->take(50)
                ->get()
                ->groupBy(fn (StockTransaction $transaction): string => $transaction->transaction_group ?: 'legacy-'.$transaction->id)
                ->take(10)
                ->map(function ($items) {
                    $firstItem = $items->first();

                    return [
                        'transaction_at' => $firstItem->transaction_at,
                        'purchase_request_number' => $firstItem->purchase_request_number,
                        'received_by_name' => $firstItem->received_by_name,
                        'notes' => $firstItem->notes,
                        'items' => $items,
                    ];
                })
                ->values(),
        ]);
    }

    public function storeReceiving(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = validator(
            $request->all(),
            [
                'transaction_date' => ['required', 'date'],
                'purchase_request_number' => ['required', 'string', 'max:255'],
                'received_by_name' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.consumable_id' => ['nullable', 'exists:consumables,id'],
                'items.*.sku_barcode' => ['nullable', 'string', 'max:255'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
            ]
        )->after(function ($validator) use ($request): void {
            foreach ($request->input('items', []) as $index => $item) {
                $consumableId = $item['consumable_id'] ?? null;
                $skuBarcode = trim((string) ($item['sku_barcode'] ?? ''));

                if (($consumableId === null || $consumableId === '') && $skuBarcode === '') {
                    $validator->errors()->add("items.{$index}.sku_barcode", 'Select a consumable or scan a SKU barcode first.');
                }
            }
        })->validate();

        $transactionGroup = (string) Str::uuid();
        $transactionAt = Carbon::parse($data['transaction_date'])->startOfDay();

        DB::transaction(function () use ($data, $transactionAt, $transactionGroup): void {
            foreach ($data['items'] as $index => $item) {
                $consumable = $this->resolveReceivingConsumable(
                    isset($item['consumable_id']) ? (string) $item['consumable_id'] : null,
                    trim((string) ($item['sku_barcode'] ?? ''))
                );

                if (! $consumable) {
                    throw ValidationException::withMessages([
                        "items.{$index}.sku_barcode" => 'Consumable with the selected item or scanned SKU barcode was not found.',
                    ]);
                }

                $before = $consumable->stock;
                $after = $before + (int) $item['quantity'];

                $consumable->update(['stock' => $after]);

                StockTransaction::create([
                    'consumable_id' => $consumable->id,
                    'transaction_type' => 'Receiving',
                    'transaction_group' => $transactionGroup,
                    'purchase_request_number' => $data['purchase_request_number'],
                    'received_by_name' => $data['received_by_name'],
                    'quantity_before' => $before,
                    'quantity_change' => (int) $item['quantity'],
                    'quantity_after' => $after,
                    'notes' => $data['notes'] ?? null,
                    'performed_by' => Auth::id(),
                    'transaction_at' => $transactionAt,
                ]);
            }
        });

        return back()->with('success', 'Receiving transaction posted.');
    }

    public function adjustment(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.inventory.adjustment', [
            'consumables' => Consumable::query()->orderBy('name')->get(),
            'latestTransactions' => StockTransaction::query()
                ->with('consumable')
                ->where('transaction_type', 'Adjustment')
                ->latest('transaction_at')
                ->take(10)
                ->get(),
        ]);
    }

    public function storeAdjustment(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'consumable_id' => ['required', 'exists:consumables,id'],
            'direction' => ['required', 'in:Increase,Decrease'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $consumable = Consumable::query()->findOrFail($data['consumable_id']);
        $before = $consumable->stock;
        $change = $data['direction'] === 'Increase' ? (int) $data['quantity'] : -1 * (int) $data['quantity'];
        $after = max(0, $before + $change);

        $consumable->update(['stock' => $after]);

        StockTransaction::create([
            'consumable_id' => $consumable->id,
            'transaction_type' => 'Adjustment',
            'quantity_before' => $before,
            'quantity_change' => $after - $before,
            'quantity_after' => $after,
            'notes' => $data['notes'] ?? null,
            'performed_by' => Auth::id(),
            'transaction_at' => now(),
        ]);

        return back()->with('success', 'Stock adjustment posted.');
    }

    public function opname(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.inventory.opname', [
            'consumables' => Consumable::query()->orderBy('name')->get(),
            'latestTransactions' => StockTransaction::query()
                ->with('consumable')
                ->where('transaction_type', 'Opname')
                ->latest('transaction_at')
                ->take(10)
                ->get(),
        ]);
    }

    public function storeOpname(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'consumable_id' => ['required', 'exists:consumables,id'],
            'actual_stock' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $consumable = Consumable::query()->findOrFail($data['consumable_id']);
        $before = $consumable->stock;
        $after = (int) $data['actual_stock'];

        $consumable->update(['stock' => $after]);

        StockTransaction::create([
            'consumable_id' => $consumable->id,
            'transaction_type' => 'Opname',
            'quantity_before' => $before,
            'quantity_change' => $after - $before,
            'quantity_after' => $after,
            'notes' => $data['notes'] ?? null,
            'performed_by' => Auth::id(),
            'transaction_at' => now(),
        ]);

        return back()->with('success', 'Stock opname reconciled.');
    }

    public function history(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = StockTransaction::query()->with(['consumable', 'performer'])->latest('transaction_at');

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder->where('purchase_request_number', 'like', '%'.$request->string('q').'%')
                    ->orWhere('received_by_name', 'like', '%'.$request->string('q').'%')
                    ->orWhereHas('consumable', function ($consumableBuilder) use ($request) {
                        $consumableBuilder->where('name', 'like', '%'.$request->string('q').'%');
                    });
            });
        }

        if ($request->filled('type')) {
            $query->where('transaction_type', $request->string('type'));
        }

        return view('administration.inventory.history', [
            'rows' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('q', 'type'),
        ]);
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }

    private function resolveReceivingConsumable(?string $consumableId, string $scannedCode): ?Consumable
    {
        if ($consumableId !== null && $consumableId !== '') {
            return Consumable::query()->find($consumableId);
        }

        if ($scannedCode === '') {
            return null;
        }

        return Consumable::query()
            ->where('sku_barcode', $scannedCode)
            ->orWhere('sku', $scannedCode)
            ->first();
    }
}
