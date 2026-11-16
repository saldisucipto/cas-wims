<?php

namespace App\Services\ManpowerPlanning;

final readonly class PlanningResult
{
    /**
     * @param  array<string, DivisionResult>  $divisions
     * @param  array<string, DeviceResult>  $devices
     * @param  array<int, string>  $manpowerBottlenecks
     * @param  array<int, string>  $deviceBottlenecks
     * @param  array<string, array{shifts: int, activities: array<int, array{code: string, name: string, required_one_shift: int, required_per_shift: int, shifts: int}>}>  $capacityShifts
     */
    public function __construct(
        public array $divisions,
        public float $effectiveHours,
        public array $devices = [],
        public int $recommendedShifts = 1,
        public array $capacityShifts = [],
        public string $overallStatus = 'FEASIBLE',
        public bool $manpowerFeasible = true,
        public bool $deviceFeasible = true,
        public array $manpowerBottlenecks = [],
        public array $deviceBottlenecks = [],
    ) {}
}
