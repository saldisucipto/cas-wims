<?php

namespace App\Services\ManpowerPlanning;

final readonly class DivisionResult
{
    /**
     * @param  array<int, ActivityResult>  $activities
     * @param  array<int, string>  $bottlenecks
     * @param  array<int, ShiftActivityResult>  $shift1
     * @param  array<int, ShiftActivityResult>  $shift2
     */
    public function __construct(
        public string $division,
        public float $sourceVolume,
        public string $sourceUnit,
        public array $activities,
        public int $recommendedShifts,
        public string $status,
        public array $bottlenecks,
        public int $totalMppOneShift,
        public int $totalMppPerShift,
        public int $minimumShift,
        public ?string $reason,
        public array $shift1,
        public array $shift2,
        public int $totalMpp,
    ) {}
}
