<?php

namespace App\Services\ManpowerPlanning\Concerns;

use App\Models\ManpowerActivity;
use App\Models\ShiftDefinition;
use App\Models\SystemSetting;

/**
 * The productivity standard a picking or packing station is measured against.
 *
 * A standard is expressed in ORDERS per hour per worker (packer 24, picker 30).
 * Each role service keeps its own demand unit, so this trait only answers
 * "which order rate is the bar" plus "how far is every worker from that bar".
 */
trait UsesProductivityStandard
{
    /**
     * Productive hours of a shift: the span the operator typed minus the break.
     *
     * This is the number every capacity calculation must use. A shift typed as
     * 08:00-17:00 with a 60 minute break is nine clock hours but eight hours of
     * actual work, and only the eight produce orders.
     */
    protected function effectiveHoursForShift(int $shift): float
    {
        return $this->shiftDefinitionFor($shift)['productive_hours'] ?? $this->legacyEffectiveHours();
    }

    /**
     * Alias kept for the capacity math, so the call sites read as what they are.
     */
    protected function productiveHoursForShift(int $shift): float
    {
        return $this->effectiveHoursForShift($shift);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shiftDefinitionFor(int $shift): ?array
    {
        $definitions = $this->regularShiftDefinitions();

        return $definitions[$shift - 1] ?? $definitions[0] ?? null;
    }

    private function legacyEffectiveHours(): float
    {
        $configured = $this->settingValue('effective_working_hours');

        if ($configured !== null && $configured > 0) {
            return $configured;
        }

        $duration = $this->settingValue('shift_duration') ?? 8.0;
        $nonProductive = $this->settingValue('non_productive_hours') ?? 0.0;

        return max(0.5, $duration - $nonProductive);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function shiftDefinitions(): array
    {
        return ShiftDefinition::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['code', 'name', 'start_time', 'end_time', 'break_minutes', 'effective_hours', 'is_short_day'])
            ->map(function (ShiftDefinition $definition) {
                $wallHours = $this->wallHours($definition->start_time, $definition->end_time);
                $breakHours = max(0, (int) $definition->break_minutes) / 60;

                return [
                    'code' => $definition->code,
                    'name' => $definition->name,
                    'start_time' => $definition->start_time,
                    'end_time' => $definition->end_time,
                    'break_minutes' => (int) $definition->break_minutes,
                    'wall_hours' => $wallHours,
                    'productive_hours' => round(max(0.0, $wallHours - $breakHours), 2),
                    'effective_hours' => (float) $definition->effective_hours,
                    'is_short_day' => (bool) $definition->is_short_day,
                ];
            })
            ->all();
    }

    /**
     * Declared span between two clock times, eight hours when it cannot be read.
     */
    private function wallHours(?string $startTime, ?string $endTime): float
    {
        $start = $this->minutesOfDay($startTime);
        $end = $this->minutesOfDay($endTime);

        if ($start === null || $end === null) {
            return 8.0;
        }

        if ($end < $start) {
            $end += 24 * 60;
        }

        return round(($end - $start) / 60, 2);
    }

    private function minutesOfDay(?string $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1]) * 60 + (int) $matches[2];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function regularShiftDefinitions(): array
    {
        return array_values(array_filter(
            $this->shiftDefinitions(),
            fn (array $definition) => $definition['is_short_day'] === false,
        ));
    }

    /**
     * Order rate the station must clear, per worker, per productive hour.
     */
    protected function productivityStandard(string $settingKey, float $fallbackOrdersPerHour): float
    {
        $configured = $this->settingValue($settingKey);

        return $configured !== null && $configured > 0 ? $configured : $fallbackOrdersPerHour;
    }

    /**
     * Rank measured workers against the standard.
     *
     * Ordering: workers already at or above the standard come first, best rate
     * to worst, so the closest names are always visible at the top. Workers
     * below the bar follow, best to worst.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function rankAgainstStandard(array $rows, float $standard): array
    {
        foreach ($rows as $index => $row) {
            $rate = (float) ($row['unit_rate'] ?? 0);

            $rows[$index]['standard_rate'] = round($standard, 2);
            $rows[$index]['meets_standard'] = $standard > 0 && $rate >= $standard;
            $rows[$index]['gap_percent'] = $standard > 0
                ? round(($rate - $standard) / $standard * 100, 1)
                : null;
        }

        usort($rows, function (array $a, array $b): int {
            if ($a['meets_standard'] !== $b['meets_standard']) {
                return $a['meets_standard'] ? -1 : 1;
            }

            return $b['unit_rate'] <=> $a['unit_rate'];
        });

        return $rows;
    }

    protected function masterActivityRate(string $code): ?float
    {
        $activity = ManpowerActivity::query()->where('code', $code)->first();

        if ($activity === null || (float) $activity->productivity_per_hour <= 0) {
            return null;
        }

        return (float) $activity->productivity_per_hour;
    }

    protected function settingValue(string $key): ?float
    {
        $value = SystemSetting::query()->where('setting_key', $key)->value('setting_value');

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }
}
