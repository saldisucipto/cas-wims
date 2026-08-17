<?php

namespace App\Http\Controllers;

use App\Models\MesonImportBatch;
use App\Models\MesonTransaction;
use App\Models\WmsAccount;
use App\Services\PackingProductivity\PackingProductivityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

class PackingProductivityController extends Controller
{
    private const PENDING_SESSION_KEY = 'meson_import_pending';

    public function __construct(private PackingProductivityService $service) {}

    public function index(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $filters = $request->only(['start_date', 'end_date', 'warehouse_id', 'operator_id', 'function', 'transaction_type', 'status']);

        return view('administration.packing-productivity.index', [
            'data' => $this->service->dashboard($filters),
            'filters' => $filters,
            'lastBatch' => MesonImportBatch::query()->with('importer')->latest()->first(),
            'operators' => WmsAccount::query()->where('status', '!=', 'Disabled')->orderBy('username')->get(),
            'warehouses' => MesonTransaction::query()->distinct()->whereNotNull('warehouse_id')->pluck('warehouse_id')->filter()->sort()->values(),
            'functions' => WmsAccount::query()->distinct()->whereNotNull('function')->pluck('function')->filter()->sort()->values(),
            'types' => MesonTransaction::query()->distinct()->whereNotNull('transaction_type')->pluck('transaction_type')->filter()->sort()->values(),
            'statuses' => MesonTransaction::query()->distinct()->whereNotNull('status')->pluck('status')->filter()->sort()->values(),
        ]);
    }

    public function import()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.packing-productivity.import');
    }

    public function upload(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'file' => ['required', File::types(['xlsx', 'csv'])->max('20mb')],
        ]);

        $file = $data['file'];
        $extension = strtolower($file->extension());
        $path = tempnam(sys_get_temp_dir(), 'meson-import-');

        if ($path === false) {
            abort(500, 'Unable to stage the uploaded file.');
        }

        file_put_contents($path, $file->get());

        session()->put(self::PENDING_SESSION_KEY, [
            'path' => $path,
            'extension' => $extension,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'original_name' => $file->getClientOriginalName(),
        ]);

        return redirect()->route('administration.packing-productivity.preview');
    }

    public function preview()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $pending = session()->get(self::PENDING_SESSION_KEY);

        if (! $pending) {
            return redirect()->route('administration.packing-productivity.import');
        }

        try {
            $preview = $this->service->preview(
                $pending['path'],
                $pending['extension'],
                Carbon::parse($pending['start_date']),
                Carbon::parse($pending['end_date']),
            );
        } catch (ValidationException $exception) {
            $this->cleanupPending();

            return redirect()
                ->route('administration.packing-productivity.import')
                ->with('error', $exception->getMessage());
        }

        return view('administration.packing-productivity.preview', [
            'preview' => $preview,
            'start_date' => $pending['start_date'],
            'end_date' => $pending['end_date'],
            'file_name' => $pending['original_name'],
        ]);
    }

    public function confirm(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $pending = session()->get(self::PENDING_SESSION_KEY);

        if (! $pending) {
            return redirect()->route('administration.packing-productivity.import');
        }

        try {
            $batch = $this->service->commit(
                $pending['path'],
                $pending['extension'],
                Carbon::parse($pending['start_date']),
                Carbon::parse($pending['end_date']),
                $pending['original_name'],
                Auth::id(),
            );
        } catch (\Throwable $exception) {
            $this->cleanupPending();

            return redirect()
                ->route('administration.packing-productivity.import')
                ->with('error', 'Import failed and was rolled back: '.$exception->getMessage());
        }

        $this->cleanupPending();

        return redirect()
            ->route('administration.packing-productivity')
            ->with('success', "Import completed. {$batch->imported_rows} transactions imported for {$batch->start_date->format('d M Y')} - {$batch->end_date->format('d M Y')}.");
    }

    public function cancel(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $this->cleanupPending();

        return redirect()
            ->route('administration.packing-productivity.import')
            ->with('error', 'Import cancelled. No data was changed.');
    }

    public function history()
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.packing-productivity.history', [
            'batches' => MesonImportBatch::query()->with('importer')->latest()->paginate(20)->withQueryString(),
        ]);
    }

    private function cleanupPending(): void
    {
        $pending = session()->pull(self::PENDING_SESSION_KEY);

        if (is_array($pending) && isset($pending['path']) && is_file($pending['path'])) {
            @unlink($pending['path']);
        }
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
