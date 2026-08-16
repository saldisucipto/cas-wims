<?php

namespace App\Services\ManpowerPlanning;

class ManpowerPlanningEngine
{
    public const MANPOWER_FIXED = 'Fixed';

    public const MANPOWER_VARIABLE = 'Variable';

    public const MANPOWER_HYBRID = 'Hybrid';

    public const SOURCE_INBOUND = 'Inbound';

    public const SOURCE_OUTBOUND = 'Outbound';

    public const SOURCE_VAS = 'VAS';

    private const DIVISION_ORDER = ['Inbound', 'Outbound'];

    /**
     * Evaluate manpower per activity, roll it up per division with the
     * operational shift rule, then apply the warehouse device constraint.
     */
    public function run(PlanningInput $input): PlanningResult
    {
        $byDivision = [];

        foreach ($input->activities as $activity) {
            $byDivision[$activity['division']][] = $this->calculateActivity($activity, $input);
        }

        $divisionNames = $this->orderedDivisions(array_keys($byDivision));

        $minShifts = [];
        foreach ($byDivision as $division => $activities) {
            $minShifts[$division] = $this->minimumShift($division, $activities, $input);
        }

        $options = [];
        foreach ($divisionNames as $division) {
            $options[$division] = $minShifts[$division] === 1 ? [1, 2] : [$minShifts[$division]];
        }

        $configs = $this->enumerateConfigs($divisionNames, $options);
        $firstEvaluation = $this->evaluateAllocation($byDivision, $configs[0], $input);

        $chosenConfig = null;
        $chosenEvaluation = null;

        foreach ($configs as $config) {
            $evaluation = $this->evaluateAllocation($byDivision, $config, $input);

            if ($evaluation['feasible']) {
                $chosenConfig = $config;
                $chosenEvaluation = $evaluation;
                break;
            }
        }

        if ($chosenConfig === null) {
            $chosenConfig = end($configs);
            $chosenEvaluation = $this->evaluateAllocation($byDivision, $chosenConfig, $input);
            $manpowerBottlenecks = $chosenEvaluation['manpowerBottlenecks'];
            $deviceBottlenecks = $chosenEvaluation['deviceBottlenecks'];
        } else {
            $escalated = array_sum($chosenConfig) > array_sum($configs[0]);
            $manpowerBottlenecks = $escalated ? $firstEvaluation['manpowerBottlenecks'] : [];
            $deviceBottlenecks = $escalated ? $firstEvaluation['deviceBottlenecks'] : [];
        }

        $divisions = [];

        foreach ($divisionNames as $division) {
            $divisions[$division] = $this->buildDivisionResult(
                $division,
                $byDivision[$division],
                $chosenConfig[$division],
                $minShifts[$division],
                $input,
                $chosenEvaluation,
            );
        }

        return new PlanningResult(
            divisions: $divisions,
            effectiveHours: $input->effectiveHours,
            devices: $chosenEvaluation['devices'],
            recommendedShifts: $chosenEvaluation['recommendedShifts'],
            overallStatus: $chosenEvaluation['overallStatus'],
            manpowerFeasible: $chosenEvaluation['manpowerFeasible'],
            deviceFeasible: $chosenEvaluation['deviceFeasible'],
            manpowerBottlenecks: $manpowerBottlenecks,
            deviceBottlenecks: $deviceBottlenecks,
        );
    }

    /**
     * @param  array<int, string>  $divisions
     * @param  array<string, array<int, int>>  $options
     * @return array<int, array<string, int>>
     */
    private function enumerateConfigs(array $divisions, array $options): array
    {
        $configs = [[]];

        foreach ($divisions as $division) {
            $next = [];

            foreach ($configs as $config) {
                foreach ($options[$division] as $count) {
                    $config[$division] = $count;
                    $next[] = $config;
                }
            }

            $configs = $next;
        }

        usort($configs, function (array $a, array $b): int {
            $sumA = array_sum($a);
            $sumB = array_sum($b);

            if ($sumA !== $sumB) {
                return $sumA <=> $sumB;
            }

            foreach (array_keys($a) as $division) {
                if ($a[$division] !== $b[$division]) {
                    return $a[$division] <=> $b[$division];
                }
            }

            return 0;
        });

        return $configs;
    }

    /**
     * @param  array<int, ActivityResult>  $activities
     */
    private function minimumShift(string $division, array $activities, PlanningInput $input): int
    {
        $rule = $input->divisionRules[$division] ?? ['minimum_shift' => 1, 'reason' => null];
        $ruleMinimum = (int) ($rule['minimum_shift'] ?? 1);

        $windowMinimum = 1;
        foreach ($activities as $activity) {
            if (! in_array(1, $activity->allowedShifts, true)) {
                $windowMinimum = 2;
            }
        }

        return max($ruleMinimum, $windowMinimum);
    }

    /**
     * @param  array{
     *     division: string,
     *     name: string,
     *     code: string,
     *     workload_source: string,
     *     workload_unit: string,
     *     conversion_ratio: float,
     *     productivity_per_hour: float,
     *     productivity_unit: string,
     *     manpower_type: string,
     *     minimum_manpower: int|null,
     *     available_manpower: int,
     *     device_type: string|null,
     *     allowed_shifts: string,
     *     start_time: string,
     *     end_time: string
     * }  $activity
     */
    private function calculateActivity(array $activity, PlanningInput $input): ActivityResult
    {
        $workloadSource = $activity['workload_source'];
        $conversionRatio = (float) $activity['conversion_ratio'];
        $productivityPerHour = (float) $activity['productivity_per_hour'];
        $manpowerType = $activity['manpower_type'];
        $minimumManpower = $activity['minimum_manpower'];
        $availableManpower = (int) $activity['available_manpower'];
        $deviceType = $activity['device_type'] ?? null;
        $allowedShifts = $this->parseAllowedShifts($activity['allowed_shifts'] ?? 'S1,S2');
        $startTime = $activity['start_time'] ?? '07:00';
        $endTime = $activity['end_time'] ?? '23:00';

        $workload = $this->resolveSourceVolume($workloadSource, $input) * $conversionRatio;
        $capacityPerShift = $productivityPerHour * $input->effectiveHours;

        if ($manpowerType === self::MANPOWER_FIXED) {
            $required = (int) ($minimumManpower ?? 0);

            return new ActivityResult(
                division: $activity['division'],
                name: $activity['name'],
                code: $activity['code'],
                workloadSource: $workloadSource,
                workloadUnit: $activity['workload_unit'],
                conversionRatio: $conversionRatio,
                productivityPerHour: $productivityPerHour,
                productivityUnit: $activity['productivity_unit'],
                manpowerType: $manpowerType,
                deviceType: $deviceType,
                allowedShifts: $allowedShifts,
                startTime: $startTime,
                endTime: $endTime,
                minimumManpower: $minimumManpower,
                availableManpower: $availableManpower,
                isWorkloadDriven: false,
                workload: $workload,
                requiredOneShift: $required,
                requiredTwoShifts: $required,
                oneShiftFeasible: $required <= $availableManpower,
                twoShiftFeasible: $required <= $availableManpower,
                recommendedShifts: 1,
                status: $required <= $availableManpower ? 'Feasible' : 'Shortage',
                shortagePerShift: max(0, $required - $availableManpower),
            );
        }

        $requiredOneShift = $this->calculateRequired($workload, $capacityPerShift);
        $requiredTwoShifts = $this->calculateRequired($workload / 2, $capacityPerShift);

        if ($manpowerType === self::MANPOWER_HYBRID) {
            $requiredOneShift = max((int) ($minimumManpower ?? 0), $requiredOneShift);
            $requiredTwoShifts = max((int) ($minimumManpower ?? 0), $requiredTwoShifts);
        }

        $oneShiftFeasible = $requiredOneShift <= $availableManpower;
        $twoShiftFeasible = $requiredTwoShifts <= $availableManpower;

        $recommendedShifts = $oneShiftFeasible ? 1 : ($twoShiftFeasible ? 2 : 0);

        return new ActivityResult(
            division: $activity['division'],
            name: $activity['name'],
            code: $activity['code'],
            workloadSource: $workloadSource,
            workloadUnit: $activity['workload_unit'],
            conversionRatio: $conversionRatio,
            productivityPerHour: $productivityPerHour,
            productivityUnit: $activity['productivity_unit'],
            manpowerType: $manpowerType,
            deviceType: $deviceType,
            allowedShifts: $allowedShifts,
            startTime: $startTime,
            endTime: $endTime,
            minimumManpower: $minimumManpower,
            availableManpower: $availableManpower,
            isWorkloadDriven: true,
            workload: $workload,
            requiredOneShift: $requiredOneShift,
            requiredTwoShifts: $requiredTwoShifts,
            oneShiftFeasible: $oneShiftFeasible,
            twoShiftFeasible: $twoShiftFeasible,
            recommendedShifts: $recommendedShifts,
            status: match ($recommendedShifts) {
                1 => 'Feasible',
                2 => 'Feasible (2 Shift)',
                default => 'Shortage',
            },
            shortagePerShift: $twoShiftFeasible ? 0 : max(0, $requiredTwoShifts - $availableManpower),
        );
    }

    /**
     * @param  array<string, array<int, ActivityResult>>  $byDivision
     * @param  array<string, int>  $shiftCounts
     * @return array{
     *     feasible: bool,
     *     manpowerFeasible: bool,
     *     deviceFeasible: bool,
     *     manpowerBottlenecks: array<int, string>,
     *     deviceBottlenecks: array<int, string>,
     *     devices: array<string, DeviceResult>,
     *     divisionPlans: array<string, array{shift1: array<int, ShiftActivityResult>, shift2: array<int, ShiftActivityResult>}>,
     *     recommendedShifts: int,
     *     overallStatus: string
     * }
     */
    private function evaluateAllocation(array $byDivision, array $shiftCounts, PlanningInput $input): array
    {
        $divisionPlans = [];
        $deviceShift1 = [];
        $deviceShift2 = [];
        $manpowerFeasible = true;
        $manpowerBottlenecks = [];

        foreach ($byDivision as $division => $activities) {
            $shiftCount = $shiftCounts[$division] ?? 1;
            $shift1 = [];
            $shift2 = [];

            foreach ($activities as $activity) {
                $mppShift1 = $this->mppForShift($activity, 1, $shiftCount);
                $mppShift2 = $this->mppForShift($activity, 2, $shiftCount);

                if ($mppShift1 !== null) {
                    $shift1[] = new ShiftActivityResult($activity->name, $activity->code, $mppShift1, $activity->deviceType, $activity->startTime, $activity->endTime);

                    if ($activity->deviceType !== null) {
                        $deviceShift1[$activity->deviceType] = ($deviceShift1[$activity->deviceType] ?? 0) + $mppShift1;
                    }

                    if ($activity->isWorkloadDriven && $mppShift1 > $activity->availableManpower) {
                        $manpowerFeasible = false;
                        $manpowerBottlenecks[] = $activity->name;
                    }
                }

                if ($mppShift2 !== null) {
                    $shift2[] = new ShiftActivityResult($activity->name, $activity->code, $mppShift2, $activity->deviceType, $activity->startTime, $activity->endTime);

                    if ($activity->deviceType !== null) {
                        $deviceShift2[$activity->deviceType] = ($deviceShift2[$activity->deviceType] ?? 0) + $mppShift2;
                    }

                    if ($activity->isWorkloadDriven && $mppShift2 > $activity->availableManpower) {
                        $manpowerFeasible = false;
                        $manpowerBottlenecks[] = $activity->name;
                    }
                }
            }

            $divisionPlans[$division] = ['shift1' => $shift1, 'shift2' => $shift2];
        }

        $deviceTypes = array_keys($input->devices);

        foreach ([$deviceShift1, $deviceShift2] as $map) {
            foreach (array_keys($map) as $type) {
                if (! in_array($type, $deviceTypes, true)) {
                    $deviceTypes[] = $type;
                }
            }
        }

        $deviceTypes = array_values(array_unique($deviceTypes));
        sort($deviceTypes);

        $devices = [];
        $deviceFeasible = true;
        $deviceBottlenecks = [];

        foreach ($deviceTypes as $type) {
            $ready = (int) ($input->devices[$type] ?? 0);
            $shift1 = $deviceShift1[$type] ?? 0;
            $shift2 = $deviceShift2[$type] ?? 0;
            $physical = max($shift1, $shift2);
            $shortage = max(0, $physical - $ready);

            if ($shortage > 0) {
                $deviceFeasible = false;
                $deviceBottlenecks[] = $type;
            }

            $devices[$type] = new DeviceResult(
                deviceType: $type,
                readyQuantity: $ready,
                requiredOneShift: $shift1,
                requiredPerShift: $shift2,
                physicalRequired: $physical,
                shortage: $shortage,
                status: $shortage > 0 ? 'SHORTAGE' : 'FEASIBLE',
            );
        }

        $feasible = $manpowerFeasible && $deviceFeasible;

        $maxShift = 1;
        foreach ($shiftCounts as $count) {
            $maxShift = max($maxShift, $count);
        }

        return [
            'feasible' => $feasible,
            'manpowerFeasible' => $manpowerFeasible,
            'deviceFeasible' => $deviceFeasible,
            'manpowerBottlenecks' => array_values(array_unique($manpowerBottlenecks)),
            'deviceBottlenecks' => array_values(array_unique($deviceBottlenecks)),
            'devices' => $devices,
            'divisionPlans' => $divisionPlans,
            'recommendedShifts' => $feasible ? $maxShift : 0,
            'overallStatus' => $feasible ? 'FEASIBLE' : 'CRITICAL',
        ];
    }

    /**
     * @param  array<int, ActivityResult>  $activities
     * @param  array{
     *     feasible: bool,
     *     manpowerFeasible: bool,
     *     deviceFeasible: bool,
     *     manpowerBottlenecks: array<int, string>,
     *     deviceBottlenecks: array<int, string>,
     *     devices: array<string, DeviceResult>,
     *     divisionPlans: array<string, array{shift1: array<int, ShiftActivityResult>, shift2: array<int, ShiftActivityResult>}>,
     *     recommendedShifts: int,
     *     overallStatus: string
     * }  $evaluation
     */
    private function buildDivisionResult(string $division, array $activities, int $shiftCount, int $minimumShift, PlanningInput $input, array $evaluation): DivisionResult
    {
        $plan = $evaluation['divisionPlans'][$division];

        $divisionFeasible = true;

        foreach ($activities as $activity) {
            if (! $activity->isWorkloadDriven) {
                continue;
            }

            $mppShift1 = $this->mppForShift($activity, 1, $shiftCount);
            $mppShift2 = $this->mppForShift($activity, 2, $shiftCount);

            if (($mppShift1 !== null && $mppShift1 > $activity->availableManpower) || ($mppShift2 !== null && $mppShift2 > $activity->availableManpower)) {
                $divisionFeasible = false;
                break;
            }
        }

        $bottlenecks = [];

        foreach ($activities as $activity) {
            if (! $activity->isWorkloadDriven) {
                continue;
            }

            if ($minimumShift === 2 || ! $divisionFeasible) {
                $mppShift1 = $this->mppForShift($activity, 1, $shiftCount);
                $mppShift2 = $this->mppForShift($activity, 2, $shiftCount);

                if (($mppShift1 !== null && $mppShift1 > $activity->availableManpower) || ($mppShift2 !== null && $mppShift2 > $activity->availableManpower)) {
                    $bottlenecks[] = $activity->name;
                }
            } elseif ($shiftCount === 2 && $activity->requiredOneShift > $activity->availableManpower) {
                $bottlenecks[] = $activity->name;
            }
        }

        $totalMpp = 0;
        foreach ($plan['shift1'] as $entry) {
            $totalMpp += $entry->mpp;
        }
        foreach ($plan['shift2'] as $entry) {
            $totalMpp += $entry->mpp;
        }

        [$sourceVolume, $sourceUnit] = $this->divisionSource($division, $input);

        $rule = $input->divisionRules[$division] ?? ['reason' => null];

        return new DivisionResult(
            division: $division,
            sourceVolume: $sourceVolume,
            sourceUnit: $sourceUnit,
            activities: $activities,
            recommendedShifts: $divisionFeasible ? $shiftCount : 0,
            status: $divisionFeasible ? 'FEASIBLE' : 'CRITICAL',
            bottlenecks: $bottlenecks,
            totalMppOneShift: array_sum(array_map(fn (ActivityResult $result) => $result->requiredOneShift, $activities)),
            totalMppPerShift: array_sum(array_map(fn (ActivityResult $result) => $result->requiredTwoShifts, $activities)),
            minimumShift: $minimumShift,
            reason: $rule['reason'] ?? null,
            shift1: $plan['shift1'],
            shift2: $plan['shift2'],
            totalMpp: $totalMpp,
        );
    }

    private function mppForShift(ActivityResult $activity, int $shift, int $shiftCount): ?int
    {
        if ($shiftCount === 1) {
            return ($shift === 1 && in_array(1, $activity->allowedShifts, true)) ? $activity->requiredOneShift : null;
        }

        if (! in_array($shift, $activity->allowedShifts, true)) {
            return null;
        }

        return count($activity->allowedShifts) === 1 ? $activity->requiredOneShift : $activity->requiredTwoShifts;
    }

    /**
     * @return array<int, int>
     */
    private function parseAllowedShifts(string $allowed): array
    {
        $shifts = [];

        foreach (explode(',', $allowed) as $segment) {
            $segment = trim($segment);

            if ($segment === 'S1') {
                $shifts[] = 1;
            } elseif ($segment === 'S2') {
                $shifts[] = 2;
            }
        }

        return $shifts === [] ? [1, 2] : $shifts;
    }

    private function resolveSourceVolume(string $source, PlanningInput $input): float
    {
        return match ($source) {
            self::SOURCE_INBOUND => (float) $input->inboundVolume,
            self::SOURCE_OUTBOUND => (float) $input->outboundVolume,
            self::SOURCE_VAS => (float) $input->vasVolume,
            default => 0.0,
        };
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function divisionSource(string $division, PlanningInput $input): array
    {
        return match ($division) {
            self::SOURCE_INBOUND => [(float) $input->inboundVolume, 'pcs'],
            self::SOURCE_OUTBOUND => [(float) $input->outboundVolume, 'order'],
            default => [0.0, ''],
        };
    }

    private function calculateRequired(float $workload, float $capacityPerShift): int
    {
        if ($workload <= 0) {
            return 0;
        }

        if ($capacityPerShift <= 0) {
            return PHP_INT_MAX;
        }

        return (int) ceil($workload / $capacityPerShift);
    }

    /**
     * @param  array<int, string>  $divisions
     * @return array<int, string>
     */
    private function orderedDivisions(array $divisions): array
    {
        $known = array_values(array_intersect(self::DIVISION_ORDER, $divisions));
        $rest = array_values(array_diff($divisions, self::DIVISION_ORDER));

        sort($rest);

        return array_merge($known, $rest);
    }
}
