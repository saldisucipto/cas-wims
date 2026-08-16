<?php

namespace App\Services\ManpowerPlanning;

final readonly class DeviceResult
{
    public function __construct(
        public string $deviceType,
        public int $readyQuantity,
        public int $requiredOneShift,
        public int $requiredPerShift,
        public int $physicalRequired,
        public int $shortage,
        public string $status,
    ) {}
}
