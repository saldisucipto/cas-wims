<?php

namespace App\Http\Controllers;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkStockTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AtkController extends Controller
{
    public function master(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = AtkItem::query()->orderBy('name');

        if ($request->filled('q')) {
            $query->where(function ($builder) use ($request) {
                $builder->where('code', 'like', '%'.$request->string('q').'%')
                    ->orWhere('name', 'like', '%'.$request->string('q').'%')
                    ->orWhere('category', 'like', '%'.$request->string('q').'%')
                    ->orWhere('unit', 'like', '%'.$request->string('q').'%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('administration.master.atk-items', [
            'rows' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only('q', 'category', 'status'),
            'categories' => AtkItem::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function storeMaster(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:atk_items,code'],
            'name' => ['required', 'string', 'max:255', 'unique:atk_items,name'],
            'category' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        AtkItem::query()->create($data);

        return back()->with('success', 'ATK item created.');
    }

    public function updateMaster(Request $request, AtkItem $atkItem)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:atk_items,code,'.$atkItem->id],
            'name' => ['required', 'string', 'max:255', 'unique:atk_items,name,'.$atkItem->id],
            'category' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        $atkItem->update($data);

        return back()->with('success', 'ATK item updated.');
    }

    public function destroyMaster(AtkItem $atkItem)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $atkItem->delete();

        return back()->with('success', 'ATK item deleted.');
    }

    public function receiving()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.inventory.atk-receiving', [
            'atkItems' => AtkItem::query()->where('status', 'Active')->orderBy('name')->get(),
            'latestReceivings' => AtkStockTransaction::query()
                ->with('atkItem')
                ->where('transaction_type', 'Receiving')
                ->latest('transaction_at')
                ->take(50)
                ->get()
                ->groupBy(fn (AtkStockTransaction $transaction): string => $transaction->transaction_number ?: 'legacy-'.$transaction->id)
                ->take(10)
                ->map(function ($items) {
                    $firstItem = $items->first();

                    return [
                        'transaction_at' => $firstItem->transaction_at,
                        'transaction_number' => $firstItem->transaction_number,
                        'supplier' => $firstItem->supplier,
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

        $data = $request->validate([
            'transaction_number' => ['required', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.atk_item_id' => ['required', 'exists:atk_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $transactionAt = Carbon::parse($data['transaction_date'])->startOfDay();

        DB::transaction(function () use ($data, $transactionAt): void {
            foreach ($data['items'] as $item) {
                $atkItem = AtkItem::query()->lockForUpdate()->findOrFail($item['atk_item_id']);
                $before = $atkItem->current_stock;
                $after = $before + (int) $item['quantity'];

                $atkItem->update(['current_stock' => $after]);

                AtkStockTransaction::query()->create([
                    'atk_item_id' => $atkItem->id,
                    'transaction_number' => $data['transaction_number'],
                    'transaction_type' => 'Receiving',
                    'reference' => $data['transaction_number'],
                    'supplier' => $data['supplier'] ?: null,
                    'quantity_in' => (int) $item['quantity'],
                    'quantity_out' => 0,
                    'balance' => $after,
                    'notes' => $data['notes'] ?? null,
                    'performed_by' => Auth::id(),
                    'transaction_at' => $transactionAt,
                ]);
            }
        });

        return back()->with('success', 'ATK receiving transaction posted.');
    }

    public function requests()
    {
        if ($redirect = $this->ensureAdminOrLeader()) {
            return $redirect;
        }

        return view('atk.requests', [
            'atkItems' => AtkItem::query()->where('status', 'Active')->orderBy('name')->get(),
            'requestHistory' => AtkRequest::query()
                ->with(['items.atkItem', 'approver', 'rejector'])
                ->where('requested_by', Auth::id())
                ->latest('requested_at')
                ->paginate(10),
            'backRoute' => Auth::user()?->role === 'Leader' ? route('leader.panel') : route('administration.dashboard'),
        ]);
    }

    public function take()
    {
        if ($redirect = $this->ensureAdminOrLeader()) {
            return $redirect;
        }

        return view('atk.take', [
            'atkItems' => AtkItem::query()->where('status', 'Active')->orderBy('name')->get(),
            'latestTakes' => AtkStockTransaction::query()
                ->with(['atkItem', 'performer'])
                ->where('transaction_type', 'Direct Take')
                ->latest('transaction_at')
                ->take(50)
                ->get()
                ->groupBy(fn (AtkStockTransaction $transaction): string => $transaction->transaction_number ?: 'legacy-'.$transaction->id)
                ->take(10)
                ->map(function ($items) {
                    $firstItem = $items->first();

                    return [
                        'transaction_at' => $firstItem->transaction_at,
                        'transaction_number' => $firstItem->transaction_number,
                        'notes' => $firstItem->notes,
                        'taken_by' => $firstItem->performer?->name ?? '-',
                        'items' => $items,
                    ];
                })
                ->values(),
            'backRoute' => Auth::user()?->role === 'Leader' ? route('leader.panel') : route('administration.dashboard'),
        ]);
    }

    public function storeRequest(Request $request)
    {
        if ($redirect = $this->ensureAdminOrLeader()) {
            return $redirect;
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.atk_item_id' => ['required', 'exists:atk_items,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $nextNumber = (AtkRequest::max('id') ?? 0) + 1;
        $requestNumber = 'ATK-REQ-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        $atkRequest = AtkRequest::query()->create([
            'request_number' => $requestNumber,
            'requested_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
            'status' => 'Pending',
            'requested_at' => now(),
        ]);

        foreach ($data['items'] as $item) {
            $atkRequest->items()->create([
                'atk_item_id' => $item['atk_item_id'],
                'quantity' => (int) $item['quantity'],
            ]);
        }

        return redirect()
            ->route('atk.requests')
            ->with('success', 'ATK request submitted.');
    }

    public function storeTake(Request $request)
    {
        if ($redirect = $this->ensureAdminOrLeader()) {
            return $redirect;
        }

        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.atk_item_id' => ['required', 'exists:atk_items,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $nextNumber = (AtkStockTransaction::max('id') ?? 0) + 1;
        $transactionNumber = 'ATK-OUT-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
        $transactionAt = Carbon::parse($data['transaction_date'])->startOfDay();

        DB::transaction(function () use ($data, $transactionAt, $transactionNumber): void {
            foreach ($data['items'] as $index => $item) {
                $atkItem = AtkItem::query()->lockForUpdate()->findOrFail($item['atk_item_id']);

                if ($atkItem->current_stock < (int) $item['quantity']) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Stock '.$atkItem->name.' tidak mencukupi untuk pengambilan.',
                    ]);
                }

                $after = $atkItem->current_stock - (int) $item['quantity'];

                $atkItem->update([
                    'current_stock' => $after,
                ]);

                AtkStockTransaction::query()->create([
                    'atk_item_id' => $atkItem->id,
                    'transaction_number' => $transactionNumber,
                    'transaction_type' => 'Direct Take',
                    'reference' => 'Direct ATK Take',
                    'supplier' => null,
                    'quantity_in' => 0,
                    'quantity_out' => (int) $item['quantity'],
                    'balance' => $after,
                    'notes' => $data['notes'] ?? null,
                    'performed_by' => Auth::id(),
                    'transaction_at' => $transactionAt,
                ]);
            }
        });

        return redirect()
            ->route('atk.take')
            ->with('success', 'ATK direct take posted.');
    }

    public function approveRequest(AtkRequest $atkRequest)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        try {
            DB::transaction(function () use ($atkRequest): void {
                $requestModel = AtkRequest::query()
                    ->with('items.atkItem')
                    ->lockForUpdate()
                    ->findOrFail($atkRequest->id);

                if ($requestModel->status !== 'Pending') {
                    throw new RuntimeException('ATK request already processed.');
                }

                foreach ($requestModel->items as $index => $requestItem) {
                    $atkItem = AtkItem::query()->lockForUpdate()->findOrFail($requestItem->atk_item_id);

                    if ($atkItem->current_stock < $requestItem->quantity) {
                        throw ValidationException::withMessages([
                            'stock' => 'ATK stock for '.$atkItem->name.' is not enough for approval.',
                        ]);
                    }

                    $after = $atkItem->current_stock - $requestItem->quantity;

                    $atkItem->update([
                        'current_stock' => $after,
                    ]);

                    AtkStockTransaction::query()->create([
                        'atk_item_id' => $atkItem->id,
                        'transaction_number' => $requestModel->request_number.'-'.($index + 1),
                        'transaction_type' => 'Approval',
                        'reference' => $requestModel->request_number,
                        'supplier' => null,
                        'quantity_in' => 0,
                        'quantity_out' => $requestItem->quantity,
                        'balance' => $after,
                        'notes' => $requestModel->notes,
                        'performed_by' => Auth::id(),
                        'transaction_at' => now(),
                    ]);
                }

                $requestModel->update([
                    'status' => 'Approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_notes' => null,
                ]);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return back()->withErrors(['atk_approval' => $exception->getMessage()]);
        }

        return back()->with('success', 'ATK request approved.');
    }

    public function rejectRequest(Request $request, AtkRequest $atkRequest)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'rejection_notes' => ['required', 'string', 'max:1000'],
        ]);

        if ($atkRequest->status !== 'Pending') {
            return back()->withErrors(['atk_approval' => 'ATK request already processed.']);
        }

        $atkRequest->update([
            'status' => 'Rejected',
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_notes' => $data['rejection_notes'],
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return back()->with('success', 'ATK request rejected.');
    }

    public function stockCard(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveStockCardFilter($request);

        $query = AtkStockTransaction::query()
            ->with(['atkItem', 'performer'])
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->latest('transaction_at');

        if ($request->filled('atk_item_id')) {
            $query->where('atk_item_id', $request->integer('atk_item_id'));
        }

        if ($request->filled('category')) {
            $query->whereHas('atkItem', function ($builder) use ($request) {
                $builder->where('category', $request->string('category'));
            });
        }

        $rows = $query->paginate(15)->withQueryString();

        $allFilteredRows = (clone $query)->get();

        return view('administration.reports.atk-stock-card', [
            'rows' => $rows,
            'filter' => array_merge($filter, $request->only('atk_item_id', 'category')),
            'atkItems' => AtkItem::query()->orderBy('name')->get(),
            'categories' => AtkItem::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'summary' => [
                'total_in' => $allFilteredRows->sum('quantity_in'),
                'total_out' => $allFilteredRows->sum('quantity_out'),
                'total_items' => $allFilteredRows->pluck('atk_item_id')->unique()->count(),
            ],
        ]);
    }

    private function resolveStockCardFilter(Request $request): array
    {
        $period = $request->string('period')->toString() ?: 'this_month';
        $startDate = $request->string('start_date')->toString();
        $endDate = $request->string('end_date')->toString();

        if ($period === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            return [
                'period' => 'custom',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'start' => $start,
                'end' => $end,
                'label' => $start->translatedFormat('d F Y').' - '.$end->translatedFormat('d F Y'),
            ];
        }

        [$start, $end, $label] = match ($period) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), 'Hari Ini'],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'Bulan Ini'],
        };

        return [
            'period' => $period,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start' => $start,
            'end' => $end,
            'label' => $label,
        ];
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }

    private function ensureAdminOrLeader()
    {
        if (! Auth::check() || ! in_array(Auth::user()->role, ['Administrator', 'Leader'], true)) {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
