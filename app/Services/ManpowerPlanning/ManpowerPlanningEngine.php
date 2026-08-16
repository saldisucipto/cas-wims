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
     * Evaluate manpower requirement per activity, roll it up per division,
     * then apply the device constraint to produce a warehouse-level shift decision.
     */
    public function run(PlanningInput $input): PlanningResult
    {
        $allActivities = [];
        $activitiesByDivision = [];

        foreach ($input->activities as $activity) {
            $result = $this->calculateActivity($activity, $input);
            $activitiesByDivision[$activity['division']][] = $result;
            $allActivities[] = $result;
        }

        $divisions = [];

        foreach ($this->orderedDivisions(array_keys($activitiesByDivision)) as $division) {
            $divisions[$division] = $this->calculateDivision($division, $activitiesByDivision[$division], $input);
        }

        $overall = $this->calculateOverall($allActivities, $input);

        return new PlanningResult(
            divisions: $divisions,
            effectiveHours: $input->effectiveHours,
            devices: $overall['devices'],
            recommendedShifts: $overall['recommendedShifts'],
            overallStatus: $overall['overallStatus'],
            manpowerFeasible: $overall['manpowerFeasible'],
            deviceFeasible: $overall['deviceFeasible'],
            manpowerBottlenecks: $overall['manpowerBottlenecks'],
            deviceBottlenecks: $overall['deviceBottlenecks'],
        );
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
     *     device_type: string|null
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
     * @param  array<int, ActivityResult>  $activities
     */
    private function calculateDivision(string $division, array $activities, PlanningInput $input): DivisionResult
    {
        $workloadDriven = array_values(array_filter($activities, fn (ActivityResult $result) => $result->isWorkloadDriven));

        $allOneShift = true;
        $allTwoShift = true;

        foreach ($workloadDriven as $activity) {
            $allOneShift = $allOneShift && $activity->oneShiftFeasible;
            $allTwoShift = $allTwoShift && $activity->twoShiftFeasible;
        }

        $recommendedShifts = $allOneShift ? 1 : ($allTwoShift ? 2 : 0);

        $bottlenecks = [];

        if ($recommendedShifts === 2) {
            foreach ($workloadDriven as $activity) {
                if (! $activity->oneShiftFeasible) {
                    $bottlenecks[] = $activity->name;
                }
            }
        } elseif ($recommendedShifts === 0) {
            foreach ($workloadDriven as $activity) {
                if (! $activity->twoShiftFeasible) {
                    $bottlenecks[] = $activity->name;
                }
            }
        }

        [$sourceVolume, $sourceUnit] = $this->divisionSource($division, $input);

        return new DivisionResult(
            division: $division,
            sourceVolume: $sourceVolume,
            sourceUnit: $sourceUnit,
            activities: $activities,
            recommendedShifts: $recommendedShifts,
            status: $recommendedShifts === 0 ? 'CRITICAL' : 'FEASIBLE',
            bottlenecks: $bottlenecks,
            totalMppOneShift: array_sum(array_map(fn (ActivityResult $result) => $result->requiredOneShift, $activities)),
            totalMppPerShift: array_sum(array_map(fn (ActivityResult $result) => $result->requiredTwoShifts, $activities)),
        );
    }

    /**
     * @param  array<int, ActivityResult>  $activities
     * @return array{
     *     devices: array<string, DeviceResult>,
     *     recommendedShifts: int,
     *     overallStatus: string,
     *     manpowerFeasible: bool,
     *     deviceFeasible: bool,
     *     manpowerBottlenecks: array<int, string>,
     *     deviceBottlenecks: array<int, string>
     * }
     */
    private function calculateOverall(array $activities, PlanningInput $input): array
    {
        $workloadDriven = array_values(array_filter($activities, fn (ActivityResult $result) => $result->isWorkloadDriven));

        $oneShiftManpowerFeasible = true;
        $twoShiftManpowerFeasible = true;
        $manpowerBottleneckOne = [];
        $manpowerBottleneckTwo = [];

        foreach ($workloadDriven as $activity) {
            if (! $activity->oneShiftFeasible) {
                $oneShiftManpowerFeasible = false;
                $manpowerBottleneckOne[] = $activity->name;
            }

            if (! $activity->twoShiftFeasible) {
                $twoShiftManpowerFeasible = false;
                $manpowerBottleneckTwo[] = $activity->name;
            }
        }

        $deviceTypes = array_keys($input->devices);

        foreach ($activities as $activity) {
            if ($activity->deviceType !== null) {
                $deviceTypes[] = $activity->deviceType;
            }
        }

        $deviceTypes = array_values(array_unique($deviceTypes));
        sort($deviceTypes);

        $deviceResults = [];
        $oneShiftDeviceFeasible = true;
        $twoShiftDeviceFeasible = true;
        $deviceBottleneckOne = [];
        $deviceBottleneckTwo = [];

        foreach ($deviceTypes as $type) {
            $ready = (int) ($input->devices[$type] ?? 0);
            $requiredOneShift = $this->sumDevice($activities, $type, true);
            $requiredPerShift = $this->sumDevice($activities, $type, false);

            if ($requiredOneShift > $ready) {
                $oneShiftDeviceFeasible = false;
                $deviceBottleneckOne[] = $type;
            }

            if ($requiredPerShift > $ready) {
                $twoShiftDeviceFeasible = false;
                $deviceBottleneckTwo[] = $type;
            }

            $deviceResults[$type] = new DeviceResult(
                deviceType: $type,
                readyQuantity: $ready,
                requiredOneShift: $requiredOneShift,
                requiredPerShift: $requiredPerShift,
                physicalRequired: 0,
                shortage: 0,
                status: 'FEASIBLE',
            );
        }

        $oneShiftFeasible = $oneShiftManpowerFeasible && $oneShiftDeviceFeasible;
        $twoShiftFeasible = $twoShiftManpowerFeasible && $twoShiftDeviceFeasible;

        if ($oneShiftFeasible) {
            $recommendedShifts = 1;
            $overallStatus = 'FEASIBLE';
            $manpowerFeasible = true;
            $deviceFeasible = true;
            $manpowerBottlenecks = [];
            $deviceBottlenecks = [];
            $physicalScenario = 'one';
        } elseif ($twoShiftFeasible) {
            $recommendedShifts = 2;
            $overallStatus = 'FEASIBLE';
            $manpowerFeasible = true;
            $deviceFeasible = true;
            $manpowerBottlenecks = $manpowerBottleneckOne;
            $deviceBottlenecks = $deviceBottleneckOne;
            $physicalScenario = 'two';
        } else {
            $recommendedShifts = 0;
            $overallStatus = 'CRITICAL';
            $manpowerFeasible = $twoShiftManpowerFeasible;
            $deviceFeasible = $twoShiftDeviceFeasible;
            $manpowerBottlenecks = $manpowerBottleneckTwo;
            $deviceBottlenecks = $deviceBottleneckTwo;
            $physicalScenario = 'two';
        }

        $finalDevices = [];

        foreach ($deviceResults as $type => $device) {
            $physicalRequired = $physicalScenario === 'one' ? $device->requiredOneShift : $device->requiredPerShift;
            $shortage = max(0, $physicalRequired - $device->readyQuantity);

            $finalDevices[$type] = new DeviceResult(
                deviceType: $device->deviceType,
                readyQuantity: $device->readyQuantity,
                requiredOneShift: $device->requiredOneShift,
                requiredPerShift: $device->requiredPerShift,
                physicalRequired: $physicalRequired,
                shortage: $shortage,
                status: $shortage > 0 ? 'SHORTAGE' : 'FEASIBLE',
            );
        }

        return [
            'devices' => $finalDevices,
            'recommendedShifts' => $recommendedShifts,
            'overallStatus' => $overallStatus,
            'manpowerFeasible' => $manpowerFeasible,
            'deviceFeasible' => $deviceFeasible,
            'manpowerBottlenecks' => array_values(array_unique($manpowerBottlenecks)),
            'deviceBottlenecks' => array_values(array_unique($deviceBottlenecks)),
        ];
    }

    /**
     * @param  array<int, ActivityResult>  $activities
     */
    private function sumDevice(array $activities, string $type, bool $oneShift): int
    {
        $sum = 0;

        foreach ($activities as $activity) {
            if ($activity->deviceType !== $type) {
                continue;
            }

            $sum += $oneShift ? $activity->requiredOneShift : $activity->requiredTwoShifts;
        }

        return $sum;
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
