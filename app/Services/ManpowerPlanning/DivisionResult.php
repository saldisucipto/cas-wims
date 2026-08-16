<?php

namespace App\Services\ManpowerPlanning;

final readonly class DivisionResult
{
    /**
     * @param  array<int, ActivityResult>  $activities
     * @param  array<int, string>  $bottlenecks
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
    ) {}
}
