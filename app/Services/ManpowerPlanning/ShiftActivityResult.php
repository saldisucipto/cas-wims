<?php

namespace App\Services\ManpowerPlanning;

final readonly class ShiftActivityResult
{
    public function __construct(
        public string $name,
        public string $code,
        public int $mpp,
        public ?string $deviceType,
        public string $startTime,
        public string $endTime,
    ) {}
}
