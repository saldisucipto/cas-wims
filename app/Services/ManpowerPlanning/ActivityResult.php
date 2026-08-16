<?php

namespace App\Services\ManpowerPlanning;

final readonly class ActivityResult
{
    /**
     * @param  array<int, int>  $allowedShifts
     */
    public function __construct(
        public string $division,
        public string $name,
        public string $code,
        public string $workloadSource,
        public string $workloadUnit,
        public float $conversionRatio,
        public float $productivityPerHour,
        public string $productivityUnit,
        public string $manpowerType,
        public ?string $deviceType,
        public array $allowedShifts,
        public string $startTime,
        public string $endTime,
        public ?int $minimumManpower,
        public int $availableManpower,
        public bool $isWorkloadDriven,
        public float $workload,
        public int $requiredOneShift,
        public int $requiredTwoShifts,
        public bool $oneShiftFeasible,
        public bool $twoShiftFeasible,
        public int $recommendedShifts,
        public string $status,
        public int $shortagePerShift,
    ) {}
}
