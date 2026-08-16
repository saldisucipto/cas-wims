<?php

namespace App\Services\ManpowerPlanning;

final readonly class PlanningInput
{
    /**
     * @param  array<int, array{
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
     * }>  $activities
     * @param  array<string, int>  $devices
     * @param  array<string, array{minimum_shift: int, reason: string|null}>  $divisionRules
     */
    public function __construct(
        public int $inboundVolume,
        public int $outboundVolume,
        public int $vasVolume,
        public float $effectiveHours,
        public array $activities,
        public array $devices = [],
        public array $divisionRules = [],
    ) {}
}
