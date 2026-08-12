<?php

namespace App\Http\Controllers;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkStockTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class AtkController extends Controller
{
    private const ATK_IMPORT_HEADERS = [
        'code',
        'name',
        'category',
        'unit',
        'minimum_stock',
        'current_stock',
        'status',
        'notes',
    ];

    private const ATK_IMPORT_EXAMPLE_ROWS = [
        [
            'ATK-CONTOH-1',
            'Pulpen Contoh',
            'Alat Tulis',
            'Pcs',
            '10',
            '100',
            'Active',
            'Contoh data - hapus baris ini sebelum import.',
        ],
        [
            'ATK-CONTOH-2',
            'Kertas A4 Contoh',
            'Kertas',
            'Rim',
            '5',
            '50',
            'Active',
            'Contoh data - hapus baris ini sebelum import.',
        ],
    ];

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
            'importHeaders' => self::ATK_IMPORT_HEADERS,
        ]);
    }

    public function storeMaster(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:atk_items,code'],
            'name' => ['required', 'string', 'max:255',],
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
        if ($redirect = $this->ensureAuthenticated()) {
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
                        'taken_by' => $firstItem->taken_by_name ?: ($firstItem->performer?->name ?? '-'),
                        'items' => $items,
                    ];
                })
                ->values(),
            'backRoute' => Auth::check()
                ? (Auth::user()->role === 'Leader' ? route('leader.panel') : route('administration.dashboard'))
                : url('/'),
        ]);
    }

    public function storeRequest(Request $request)
    {
        if ($redirect = $this->ensureAuthenticated()) {
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
        $data = $request->validate([
            'taken_by' => ['required', 'string', 'max:255'],
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
                    'performed_by' => Auth::id() ?: null,
                    'taken_by_name' => $data['taken_by'],
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

        $atkItems = AtkItem::query()->orderBy('name')->get();
        $categories = AtkItem::query()->select('category')->distinct()->orderBy('category')->pluck('category');
        $selectedAtkItemId = $request->integer('atk_item_id');
        $selectedCategory = $request->string('category')->toString();

        // No specific ATK selected â†’ summary list of all ATK items.
        if (! $selectedAtkItemId) {
            $summaryRows = $this->atkSummaryRows($filter, $selectedCategory);

            return view('administration.reports.atk-stock-card', [
                'mode' => 'summary',
                'rows' => $summaryRows,
                'filter' => array_merge($filter, $request->only('atk_item_id', 'category')),
                'atkItems' => $atkItems,
                'categories' => $categories,
                'summary' => [
                    'total_in' => $summaryRows->sum('total_in'),
                    'total_out' => $summaryRows->sum('total_out'),
                    'total_items' => $summaryRows->count(),
                ],
            ]);
        }

        $query = AtkStockTransaction::query()
            ->with(['atkItem', 'performer'])
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->latest('transaction_at');

        if ($selectedAtkItemId) {
            $query->where('atk_item_id', $selectedAtkItemId);
        }

        if ($selectedCategory) {
            $query->whereHas('atkItem', function ($builder) use ($selectedCategory) {
                $builder->where('category', $selectedCategory);
            });
        }

        $rows = $query->paginate(15)->withQueryString();

        $allFilteredRows = (clone $query)->get();

        return view('administration.reports.atk-stock-card', [
            'mode' => 'detail',
            'rows' => $rows,
            'filter' => array_merge($filter, $request->only('atk_item_id', 'category')),
            'atkItems' => $atkItems,
            'categories' => $categories,
            'summary' => [
                'total_in' => $allFilteredRows->sum('quantity_in'),
                'total_out' => $allFilteredRows->sum('quantity_out'),
                'total_items' => $allFilteredRows->pluck('atk_item_id')->unique()->count(),
            ],
        ]);
    }

    public function printStockCard(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filter = $this->resolveStockCardFilter($request);

        // No specific ATK selected â†’ print summary list of all ATK items.
        if (! $request->filled('atk_item_id')) {
            $summaryRows = $this->atkSummaryRows($filter, $request->string('category')->toString());

            return view('administration.reports.atk-stock-card-summary-print', [
                'rows' => $summaryRows,
                'filter' => $filter,
                'printedBy' => Auth::user()->name,
                'printedAt' => now(),
            ]);
        }

        $atkItem = AtkItem::query()->find($request->integer('atk_item_id'));

        if (! $atkItem) {
            return back()->withErrors(['atk_print' => 'Pilih ATK item terlebih dahulu.']);
        }

        $transactions = AtkStockTransaction::query()
            ->with(['performer'])
            ->where('atk_item_id', $atkItem->id)
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->orderBy('transaction_at')
            ->orderBy('id')
            ->get();

        $previousTransaction = AtkStockTransaction::query()
            ->where('atk_item_id', $atkItem->id)
            ->where('transaction_at', '<', $filter['start'])
            ->latest('transaction_at')
            ->first();

        if ($previousTransaction) {
            $openingBalance = $previousTransaction->balance;
        } else {
            $totalIn = $transactions->sum('quantity_in');
            $totalOut = $transactions->sum('quantity_out');
            $openingBalance = $atkItem->current_stock - $totalIn + $totalOut;
        }

        $runningBalance = $openingBalance;
        $totalIncoming = 0;
        $totalOutgoing = 0;

        foreach ($transactions as $transaction) {
            $totalIncoming += $transaction->quantity_in;
            $totalOutgoing += $transaction->quantity_out;
            $runningBalance = $runningBalance + $transaction->quantity_in - $transaction->quantity_out;
            $transaction->running_balance = $runningBalance;
        }

        $endingBalance = $openingBalance + $totalIncoming - $totalOutgoing;

        return view('administration.reports.atk-stock-card-print', [
            'atkItem' => $atkItem,
            'transactions' => $transactions,
            'filter' => $filter,
            'openingBalance' => $openingBalance,
            'totalIncoming' => $totalIncoming,
            'totalOutgoing' => $totalOutgoing,
            'endingBalance' => $endingBalance,
            'printedBy' => Auth::user()->name,
            'printedAt' => now(),
        ]);
    }

    private function atkSummaryRows(array $filter, string $selectedCategory = ''): Collection
    {
        $totals = AtkStockTransaction::query()
            ->select('atk_item_id')
            ->selectRaw('COALESCE(SUM(quantity_in), 0) as total_in')
            ->selectRaw('COALESCE(SUM(quantity_out), 0) as total_out')
            ->whereBetween('transaction_at', [$filter['start'], $filter['end']])
            ->when($selectedCategory, fn ($builder) => $builder->whereHas('atkItem', fn ($query) => $query->where('category', $selectedCategory)))
            ->groupBy('atk_item_id')
            ->get()
            ->keyBy('atk_item_id');

        $itemQuery = AtkItem::query()->orderBy('name');

        if ($selectedCategory) {
            $itemQuery->where('category', $selectedCategory);
        }

        return $itemQuery->get()
            ->map(function (AtkItem $atkItem) use ($totals) {
                $itemTotals = $totals->get($atkItem->id);

                return [
                    'code' => $atkItem->code,
                    'name' => $atkItem->name,
                    'category' => $atkItem->category,
                    'unit' => $atkItem->unit,
                    'total_in' => (int) ($itemTotals->total_in ?? 0),
                    'total_out' => (int) ($itemTotals->total_out ?? 0),
                    'balance' => (int) $atkItem->current_stock,
                ];
            })
            ->values();
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

    public function downloadAtkTemplate()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $temporaryFile = $this->createAtkTemplateFile();

        return response()->download(
            $temporaryFile,
            'atk-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function importAtk(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'file' => ['required', File::types(['xlsx', 'csv'])->max('5mb')],
        ]);

        $rows = $this->normalizeImportRows(
            $this->parseImportedSpreadsheetFile($data['file']),
            self::ATK_IMPORT_HEADERS
        );

        $this->guardAgainstDuplicateImportRows($rows);

        $validatedRows = validator(
            ['rows' => $rows],
            [
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.code' => ['required', 'string', 'max:255', 'distinct'],
                'rows.*.name' => ['required', 'string', 'max:255', 'distinct'],
                'rows.*.category' => ['required', 'string', 'max:255'],
                'rows.*.unit' => ['required', 'string', 'max:30'],
                'rows.*.minimum_stock' => ['required', 'integer', 'min:0'],
                'rows.*.current_stock' => ['required', 'integer', 'min:0'],
                'rows.*.status' => ['required', 'in:Active,Inactive'],
                'rows.*.notes' => ['nullable', 'string'],
            ],
            [
                'rows.min' => 'Import file does not contain any ATK rows.',
                'rows.*.code.distinct' => 'ATK code in the import file must be unique.',
                'rows.*.name.distinct' => 'ATK name in the import file must be unique.',
            ]
        )->validate()['rows'];

        [$createdCount, $updatedCount] = $this->persistImportedAtkItems($validatedRows);

        return redirect()
            ->route('administration.master.atk')
            ->with('success', "ATK import completed. {$createdCount} created, {$updatedCount} updated.");
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function guardAgainstDuplicateImportRows(array $rows): void
    {
        $messages = [];

        foreach (['code' => 'Kode ATK', 'name' => 'Nama ATK'] as $field => $label) {
            $positions = [];

            foreach ($rows as $index => $row) {
                $positions[$row[$field]][] = $index + 1;
            }

            foreach ($positions as $value => $indexes) {
                if (count($indexes) > 1) {
                    $rowList = implode(', ', array_map(
                        fn (int $index): string => 'ke-'.$index,
                        array_slice($indexes, 0, -1)
                    )).' dan ke-'.end($indexes);
                    $messages[] = sprintf(
                        '%s "%s" muncul di baris data %s.',
                        $label,
                        $value,
                        $rowList
                    );
                }
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([
                'rows' => ['Import dibatalkan: '.implode(' ', $messages).' Kode ATK dan Nama ATK harus unik dalam file import.'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{0:int,1:int}
     */
    private function persistImportedAtkItems(array $rows): array
    {
        $createdCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($rows, &$createdCount, &$updatedCount): void {
            foreach ($rows as $index => $row) {
                $itemByCode = AtkItem::query()->where('code', $row['code'])->first();
                $itemByName = AtkItem::query()->where('name', $row['name'])->first();

                if ($itemByCode && $itemByName && $itemByCode->id !== $itemByName->id) {
                    throw ValidationException::withMessages([
                        'file' => ['Row '.($index + 2).' conflicts with existing ATK code and name records.'],
                    ]);
                }

                $atkItem = $itemByCode ?? $itemByName ?? new AtkItem;
                $exists = $atkItem->exists;

                $atkItem->fill([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'unit' => $row['unit'],
                    'minimum_stock' => (int) $row['minimum_stock'],
                    'current_stock' => (int) $row['current_stock'],
                    'status' => $row['status'],
                    'notes' => $row['notes'] !== '' ? $row['notes'] : null,
                ]);

                $atkItem->save();

                if ($exists) {
                    $updatedCount++;
                } else {
                    $createdCount++;
                }
            }
        });

        return [$createdCount, $updatedCount];
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }

    private function ensureAuthenticated()
    {
        if (! Auth::check()) {
            return redirect()->route('administration.login');
        }

        return null;
    }

    private function createAtkTemplateFile(): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'atk-template-');

        if ($temporaryFile === false) {
            abort(500, 'Unable to create template file.');
        }

        $zip = new ZipArchive;

        if ($zip->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to build template file.');
        }

        $sharedStrings = self::ATK_IMPORT_HEADERS;

        foreach (self::ATK_IMPORT_EXAMPLE_ROWS as $exampleRow) {
            array_push($sharedStrings, ...$exampleRow);
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelationshipsXml());
        $zip->addFromString('docProps/app.xml', $this->xlsxAppPropertiesXml());
        $zip->addFromString('docProps/core.xml', $this->xlsxCorePropertiesXml());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbookXml('ATK'));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->xlsxStylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->xlsxSharedStringsXml($sharedStrings));
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxAtkWorksheetXml($sharedStrings));
        $zip->close();

        return $temporaryFile;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function xlsxAtkWorksheetXml(array $sharedStrings): string
    {
        $headerCount = count(self::ATK_IMPORT_HEADERS);
        $rows = [];

        $headerCells = [];

        foreach (self::ATK_IMPORT_HEADERS as $index => $header) {
            $column = $this->indexToColumnReference($index);
            $headerCells[] = '<c r="'.$column.'1" t="s"><v>'.$index.'</v></c>';
        }

        $rows[] = '<row r="1">'.implode('', $headerCells).'</row>';

        foreach (self::ATK_IMPORT_EXAMPLE_ROWS as $rowOffset => $exampleRow) {
            $rowNumber = $rowOffset + 2;
            $cells = [];

            foreach ($exampleRow as $columnIndex => $value) {
                $column = $this->indexToColumnReference($columnIndex);
                $sharedIndex = $headerCount + ($rowOffset * $headerCount) + $columnIndex;
                $cells[] = '<c r="'.$column.$rowNumber.'" t="s"><v>'.$sharedIndex.'</v></c>';
            }

            $rows[] = '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
        }

        $lastRow = count(self::ATK_IMPORT_EXAMPLE_ROWS) + 1;
        $lastColumn = $this->indexToColumnReference($headerCount - 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetData>'
            .implode('', $rows)
            .'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseImportedSpreadsheetFile(UploadedFile $file): array
    {
        $extension = strtolower($file->extension());

        if ($extension === 'csv') {
            return $this->parseImportCsv($file->getRealPath());
        }

        if ($extension === 'xlsx') {
            return $this->parseImportXlsx($file->getRealPath());
        }

        throw ValidationException::withMessages([
            'file' => ['Unsupported import file type. Use the provided XLSX template or a CSV file.'],
        ]);
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseImportCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to read the uploaded CSV file.'],
            ]);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($rows === [] && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }

            $rows[] = array_map(
                static fn ($value): ?string => $value === null ? null : (string) $value,
                $row
            );
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function parseImportXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'file' => ['Unable to open the uploaded XLSX file.'],
            ]);
        }

        $worksheetPath = $this->firstWorksheetPath($zip);
        $sheetXml = $worksheetPath ? $zip->getFromName($worksheetPath) : false;
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if (! $worksheetPath || $sheetXml === false) {
            throw ValidationException::withMessages([
                'file' => ['Unable to read worksheet data from the uploaded XLSX file.'],
            ]);
        }

        $sharedStrings = $this->parseSharedStrings($sharedStringsXml ?: null);
        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof SimpleXMLElement || ! isset($sheet->sheetData)) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded XLSX file has an invalid worksheet format.'],
            ]);
        }

        $rows = [];

        foreach ($sheet->sheetData->row as $rowNode) {
            $row = [];

            foreach ($rowNode->c as $cellNode) {
                $reference = (string) $cellNode['r'];
                $columnIndex = $this->columnReferenceToIndex($reference);
                $row[$columnIndex] = $this->cellNodeValue($cellNode, $sharedStrings);
            }

            if ($row !== []) {
                ksort($row);
                $rows[] = array_values($row);
            }
        }

        return $rows;
    }

    private function firstWorksheetPath(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function parseSharedStrings(?string $xml): array
    {
        if ($xml === null || $xml === '') {
            return [];
        }

        $sharedStringsXml = simplexml_load_string($xml);

        if (! $sharedStringsXml instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($sharedStringsXml->si as $stringNode) {
            if (isset($stringNode->t)) {
                $strings[] = (string) $stringNode->t;

                continue;
            }

            $text = '';

            foreach ($stringNode->r as $runNode) {
                $text .= (string) $runNode->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function cellNodeValue(SimpleXMLElement $cellNode, array $sharedStrings): ?string
    {
        $type = (string) $cellNode['t'];

        if ($type === 's') {
            $sharedStringIndex = (int) ($cellNode->v ?? 0);

            return $sharedStrings[$sharedStringIndex] ?? null;
        }

        if ($type === 'inlineStr') {
            return isset($cellNode->is->t) ? (string) $cellNode->is->t : null;
        }

        return isset($cellNode->v) ? (string) $cellNode->v : null;
    }

    /**
     * @param array<int, array<int, string|null>> $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeImportRows(array $rows, array $expectedHeaders): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => ['Import file is empty.'],
            ]);
        }

        $headerRow = array_shift($rows) ?? [];
        $headers = array_map(function (?string $value): string {
            return trim((string) $value);
        }, $headerRow);

        if ($headers !== $expectedHeaders) {
            throw ValidationException::withMessages([
                'file' => ['Invalid template header. Download the latest template and try again.'],
            ]);
        }

        $normalizedRows = [];

        foreach ($rows as $rowIndex => $row) {
            $mappedRow = [];

            foreach ($expectedHeaders as $columnIndex => $header) {
                $mappedRow[$header] = trim((string) ($row[$columnIndex] ?? ''));
            }

            if (count(array_filter($mappedRow, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            $normalizedRows[] = $mappedRow;
        }

        return $normalizedRows;
    }

    private function columnReferenceToIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($reference), $matches);

        $letters = $matches[0] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max($index - 1, 0);
    }

    private function indexToColumnReference(int $index): string
    {
        $reference = '';
        $number = $index + 1;

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $reference = chr(65 + $remainder).$reference;
            $number = intdiv($number - 1, 26);
        }

        return $reference;
    }

    private function xlsxContentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function xlsxRootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function xlsxAppPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Microsoft Excel</Application>'
            .'</Properties>';
    }

    private function xlsxCorePropertiesXml(): string
    {
        $timestamp = now()->toAtomString();

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>WIMS</dc:creator>'
            .'<cp:lastModifiedBy>WIMS</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function xlsxWorkbookXml(string $sheetName): string
    {
        return str_replace(
            '__SHEET_NAME__',
            e($sheetName),
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="__SHEET_NAME__" sheetId="1" r:id="rId1"/>'
            .'</sheets>'
            .'</workbook>'
        );
    }

    private function xlsxWorkbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>';
    }

    private function xlsxStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /**
     * @param array<int, string> $strings
     */
    private function xlsxSharedStringsXml(array $strings): string
    {
        $count = count($strings);
        $items = collect($strings)
            ->map(fn (string $value): string => '<si>'.e($value).'</si>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">'.$items.'</sst>';
    }
}
