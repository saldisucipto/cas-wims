<?php

namespace App\Services\ManpowerPlanning\Concerns;

use App\Models\DeviceAvailability;
use App\Models\ManpowerActivity;

/**
 * How much work one station can physically clear in a single shift.
 *
 * The ceiling is the ready equipment, not the headcount pool: a packing bench
 * is one packer, an RF is one picker, so the station count caps how many people
 * can work the order at the same time. A demand that fits the stations in one
 * shift must never be spread over two.
 */
trait UsesStationCapacity
{
    /**
     * Ready units of a device type. Zero means no ceiling is configured.
     */
    protected function readyDevices(string $deviceType): int
    {
        return max(0, (int) (DeviceAvailability::query()
            ->where('device_type', $deviceType)
            ->value('ready_quantity') ?? 0));
    }

    /**
     * Stations the activity's own master row declares it has.
     */
    protected function declaredStations(string $activityCode): int
    {
        return max(0, (int) ManpowerActivity::query()
            ->where('code', $activityCode)
            ->value('available_manpower'));
    }

    /**
     * The station count that actually limits the operation.
     */
    protected function stationCapacity(string $activityCode, string $deviceType): int
    {
        $ready = $this->readyDevices($deviceType);

        return $ready > 0 ? $ready : $this->declaredStations($activityCode);
    }
}
