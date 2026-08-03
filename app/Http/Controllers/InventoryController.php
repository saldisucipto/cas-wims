<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InventoryController extends Controller
{
    public function receiving(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.inventory.receiving', [
            'consumables' => Consumable::query()->where('is_active', true)->orderBy('name')->get(),
            'latestTransactions' => StockTransaction::query()
                ->with('consumable')
                ->where('transaction_type', 'Receiving')
                ->latest('transaction_at')
                ->take(10)
                ->get(),
        ]);
    }

    public function storeReceiving(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'consumable_id' => ['required', 'exists:consumables,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $consumable = Consumable::query()->findOrFail($data['consumable_id']);
        $before = $consumable->stock;
        $after = $before + (int) $data['quantity'];

        $consumable->update(['stock' => $after]);

        StockTransaction::create([
            'consumable_id' => $consumable->id,
            'transaction_type' => 'Receiving',
            'quantity_before' => $before,
            'quantity_change' => (int) $data['quantity'],
            'quantity_after' => $after,
            'notes' => $data['notes'] ?? null,
            'performed_by' => Auth::id(),
            'transaction_at' => now(),
        ]);

        return back()->with('success', 'Receiving posted.');
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
            $query->whereHas('consumable', function ($builder) use ($request) {
                $builder->where('name', 'like', '%' . $request->string('q') . '%');
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
}
