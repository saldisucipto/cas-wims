<?php

namespace App\Services\ManpowerPlanning\Concerns;

use App\Models\MesonTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Locates the Meson operational days that can serve as a productivity basis.
 *
 * The helper only answers "which days hold activity of these transaction types".
 * Rates, demand and headcount stay inside the role service that owns them.
 */
trait ResolvesMesonBasisDays
{
    /**
     * The most recent operational days holding the given transaction types,
     * strictly before $before, newest first.
     *
     * @param  array<int, string>  $transactionTypes
     * @return array<int, string>
     */
    protected function recentActiveDays(array $transactionTypes, Carbon $before, int $limit): array
    {
        if ($transactionTypes === [] || $limit < 1) {
            return [];
        }

        $day = $this->mesonDayExpression();

        return MesonTransaction::query()
            ->selectRaw($day.' AS day')
            ->whereIn('transaction_type', $transactionTypes)
            ->whereNotNull('transaction_time')
            ->where('transaction_time', '<', $before)
            ->groupByRaw($day)
            ->orderByDesc('day')
            ->limit($limit)
            ->pluck('day')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    protected function mesonDayExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', transaction_time)",
            'pgsql' => "to_char(transaction_time, 'YYYY-MM-DD')",
            default => 'DATE(transaction_time)',
        };
    }
}
