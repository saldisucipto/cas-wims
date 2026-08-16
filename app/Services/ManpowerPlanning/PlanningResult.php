<?php

namespace App\Services\ManpowerPlanning;

final readonly class PlanningResult
{
    /**
     * @param  array<string, DivisionResult>  $divisions
     * @param  array<string, DeviceResult>  $devices
     * @param  array<int, string>  $manpowerBottlenecks
     * @param  array<int, string>  $deviceBottlenecks
     */
    public function __construct(
        public array $divisions,
        public float $effectiveHours,
        public array $devices = [],
        public int $recommendedShifts = 1,
        public string $overallStatus = 'FEASIBLE',
        public bool $manpowerFeasible = true,
        public bool $deviceFeasible = true,
        public array $manpowerBottlenecks = [],
        public array $deviceBottlenecks = [],
    ) {}
}
