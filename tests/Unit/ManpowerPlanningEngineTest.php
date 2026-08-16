<?php

use App\Services\ManpowerPlanning\ActivityResult;
use App\Services\ManpowerPlanning\ManpowerPlanningEngine;
use App\Services\ManpowerPlanning\PlanningInput;
use App\Services\ManpowerPlanning\PlanningResult;

function mpActivity(array $overrides = []): array
{
    return array_merge([
        'division' => 'Outbound',
        'name' => 'Picker',
        'code' => 'OB-PICKER',
        'workload_source' => 'Outbound',
        'workload_unit' => 'PCS',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 100,
        'productivity_unit' => 'pcs/hour',
        'manpower_type' => 'Variable',
        'minimum_manpower' => null,
        'available_manpower' => 10,
        'device_type' => null,
    ], $overrides);
}

function inboundActivities(): array
{
    return [
        mpActivity(['division' => 'Inbound', 'name' => 'Unloading', 'code' => 'INB-UNLOAD', 'workload_source' => 'Inbound', 'workload_unit' => 'PCS', 'productivity_per_hour' => 800, 'productivity_unit' => 'pcs/hour', 'available_manpower' => 20]),
        mpActivity(['division' => 'Inbound', 'name' => 'Receiving', 'code' => 'INB-RECEIVE', 'workload_source' => 'Inbound', 'workload_unit' => 'PCS', 'productivity_per_hour' => 400, 'productivity_unit' => 'pcs/hour', 'available_manpower' => 40]),
        mpActivity(['division' => 'Inbound', 'name' => 'Putaway', 'code' => 'INB-PUTAWAY', 'workload_source' => 'Inbound', 'workload_unit' => 'PCS', 'productivity_per_hour' => 600, 'productivity_unit' => 'pcs/hour', 'available_manpower' => 50]),
    ];
}

function outboundActivities(): array
{
    return [
        mpActivity(['name' => 'Picker', 'code' => 'OB-PICKER', 'workload_unit' => 'PCS', 'conversion_ratio' => 2.8, 'productivity_per_hour' => 150, 'productivity_unit' => 'pcs/hour', 'available_manpower' => 150]),
        mpActivity(['name' => 'Check-Pack', 'code' => 'OB-CHECKPACK', 'workload_unit' => 'Order', 'conversion_ratio' => 1, 'productivity_per_hour' => 24, 'productivity_unit' => 'order/hour', 'available_manpower' => 300]),
        mpActivity(['name' => 'Dispatch', 'code' => 'OB-DISPATCH', 'workload_unit' => 'Order', 'conversion_ratio' => 1, 'productivity_per_hour' => 500, 'productivity_unit' => 'order/hour', 'available_manpower' => 30]),
        mpActivity(['name' => 'Handover', 'code' => 'OB-HANDOVER', 'workload_unit' => 'Order', 'conversion_ratio' => 1, 'productivity_per_hour' => 400, 'productivity_unit' => 'order/hour', 'available_manpower' => 30]),
    ];
}

function runPlan(array $activities, int $inboundVolume = 0, int $outboundVolume = 0, int $vasVolume = 0, array $devices = [], array $divisionRules = []): PlanningResult
{
    return (new ManpowerPlanningEngine)->run(new PlanningInput(
        inboundVolume: $inboundVolume,
        outboundVolume: $outboundVolume,
        vasVolume: $vasVolume,
        effectiveHours: 7,
        activities: $activities,
        devices: $devices,
        divisionRules: $divisionRules,
    ));
}

function findActivity(array $activities, string $name): ActivityResult
{
    foreach ($activities as $activity) {
        if ($activity->name === $name) {
            return $activity;
        }
    }

    throw new RuntimeException("Activity [{$name}] not found.");
}

test('capacity formula rounds required MPP up', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Inbound',
            'name' => 'Unloading',
            'workload_source' => 'Inbound',
            'workload_unit' => 'PCS',
            'productivity_per_hour' => 800,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 20,
        ]),
    ], inboundVolume: 10000);

    $unloading = findActivity($result->divisions['Inbound']->activities, 'Unloading');

    expect($unloading->requiredOneShift)->toBe(2);
});

test('converts outbound orders to pcs using conversion ratio', function () {
    $result = runPlan([
        mpActivity([
            'name' => 'Picker',
            'workload_unit' => 'PCS',
            'conversion_ratio' => 2.8,
            'productivity_per_hour' => 150,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 150,
        ]),
    ], outboundVolume: 5000);

    $picker = findActivity($result->divisions['Outbound']->activities, 'Picker');

    expect($picker->requiredOneShift)->toBe(14)
        ->and((int) round($picker->workload))->toBe(14000);
});

test('recommends 1 shift when all outbound activities fit available manpower', function () {
    $result = runPlan(outboundActivities(), outboundVolume: 5000);

    $outbound = $result->divisions['Outbound'];

    expect($outbound->recommendedShifts)->toBe(1)
        ->and($outbound->status)->toBe('FEASIBLE')
        ->and($outbound->bottlenecks)->toBe([])
        ->and($outbound->totalMppOneShift)->toBe(48);
});

test('recommends 2 shifts and flags check-pack as bottleneck', function () {
    $result = runPlan(outboundActivities(), outboundVolume: 52000);

    $outbound = $result->divisions['Outbound'];
    $checkPack = findActivity($outbound->activities, 'Check-Pack');

    expect($outbound->recommendedShifts)->toBe(2)
        ->and($outbound->status)->toBe('FEASIBLE')
        ->and($outbound->bottlenecks)->toBe(['Check-Pack'])
        ->and($checkPack->requiredOneShift)->toBe(310)
        ->and($checkPack->requiredTwoShifts)->toBe(155);
});

test('marks critical shortage when even 2 shifts are insufficient', function () {
    $result = runPlan(outboundActivities(), outboundVolume: 120000);

    $outbound = $result->divisions['Outbound'];
    $checkPack = findActivity($outbound->activities, 'Check-Pack');

    expect($outbound->recommendedShifts)->toBe(0)
        ->and($outbound->status)->toBe('CRITICAL')
        ->and($outbound->bottlenecks)->toContain('Check-Pack')
        ->and($checkPack->shortagePerShift)->toBe(58);
});

test('hybrid manpower applies minimum over calculated value', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Inbound',
            'name' => 'Receiving',
            'workload_source' => 'Inbound',
            'manpower_type' => 'Hybrid',
            'minimum_manpower' => 5,
            'productivity_per_hour' => 800,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 5,
        ]),
    ], inboundVolume: 5600);

    $receiving = findActivity($result->divisions['Inbound']->activities, 'Receiving');

    expect($receiving->requiredOneShift)->toBe(5);
});

test('fixed manpower is constant regardless of workload', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Inbound',
            'name' => 'Admin Inbound',
            'workload_source' => 'Inbound',
            'manpower_type' => 'Fixed',
            'minimum_manpower' => 2,
            'available_manpower' => 2,
        ]),
    ], inboundVolume: 100000);

    $admin = findActivity($result->divisions['Inbound']->activities, 'Admin Inbound');

    expect($admin->requiredOneShift)->toBe(2)
        ->and($admin->isWorkloadDriven)->toBeFalse()
        ->and($admin->recommendedShifts)->toBe(1);
});

test('vas workload comes from the schedule, not inbound volume', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Inbound',
            'name' => 'VAS',
            'workload_source' => 'VAS',
            'workload_unit' => 'PCS',
            'productivity_per_hour' => 200,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 20,
        ]),
    ], inboundVolume: 0, vasVolume: 5000);

    $vas = findActivity($result->divisions['Inbound']->activities, 'VAS');

    expect($vas->requiredOneShift)->toBe(4);
});

test('decides shift independently per division', function () {
    $result = runPlan(
        array_merge(inboundActivities(), outboundActivities()),
        inboundVolume: 10000,
        outboundVolume: 52000,
    );

    expect($result->divisions['Inbound']->recommendedShifts)->toBe(1)
        ->and($result->divisions['Outbound']->recommendedShifts)->toBe(2);
});

test('device constraint escalates to 2 shift when 1 shift device requirement exceeds ready', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Inbound',
            'name' => 'Checker',
            'workload_source' => 'Inbound',
            'productivity_per_hour' => 100,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 20,
            'device_type' => 'PC',
        ]),
    ], inboundVolume: 14000, devices: ['PC' => 10]);

    $checker = findActivity($result->divisions['Inbound']->activities, 'Checker');

    expect($checker->requiredOneShift)->toBe(20)
        ->and($checker->requiredTwoShifts)->toBe(10)
        ->and($result->recommendedShifts)->toBe(2)
        ->and($result->deviceFeasible)->toBeTrue()
        ->and($result->deviceBottlenecks)->toBe(['PC'])
        ->and($result->devices['PC']->physicalRequired)->toBe(10)
        ->and($result->devices['PC']->shortage)->toBe(0)
        ->and($result->devices['PC']->status)->toBe('FEASIBLE');
});

test('marks critical device shortage when 2 shift device still exceeds ready', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Inbound',
            'name' => 'Checker',
            'workload_source' => 'Inbound',
            'productivity_per_hour' => 100,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 50,
            'device_type' => 'PC',
        ]),
    ], inboundVolume: 30000, devices: ['PC' => 10]);

    expect($result->recommendedShifts)->toBe(0)
        ->and($result->overallStatus)->toBe('CRITICAL')
        ->and($result->deviceFeasible)->toBeFalse()
        ->and($result->manpowerFeasible)->toBeTrue()
        ->and($result->deviceBottlenecks)->toBe(['PC'])
        ->and($result->devices['PC']->shortage)->toBe(12);
});

test('device requirement is shared across shifts (peak, not sum)', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Outbound',
            'name' => 'Picker',
            'workload_source' => 'Outbound',
            'workload_unit' => 'PCS',
            'productivity_per_hour' => 100,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 50,
            'device_type' => 'RF',
        ]),
    ], outboundVolume: 30000, devices: ['RF' => 24]);

    $picker = findActivity($result->divisions['Outbound']->activities, 'Picker');

    expect($picker->requiredTwoShifts)->toBe(22)
        ->and($result->recommendedShifts)->toBe(2)
        ->and($result->devices['RF']->physicalRequired)->toBe(22)
        ->and($result->devices['RF']->shortage)->toBe(0);
});

test('can have both manpower and device shortage', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Inbound',
            'name' => 'Checker',
            'workload_source' => 'Inbound',
            'productivity_per_hour' => 100,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 10,
            'device_type' => 'PC',
        ]),
    ], inboundVolume: 30000, devices: ['PC' => 10]);

    expect($result->recommendedShifts)->toBe(0)
        ->and($result->manpowerFeasible)->toBeFalse()
        ->and($result->deviceFeasible)->toBeFalse()
        ->and($result->manpowerBottlenecks)->toBe(['Checker'])
        ->and($result->deviceBottlenecks)->toBe(['PC']);
});

test('s2-only activity allocates full workload to shift 2 only', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Outbound',
            'name' => 'Dispatch',
            'workload_source' => 'Outbound',
            'workload_unit' => 'Order',
            'productivity_per_hour' => 500,
            'productivity_unit' => 'order/hour',
            'available_manpower' => 30,
            'device_type' => 'RF',
            'allowed_shifts' => 'S2',
            'start_time' => '15:00',
            'end_time' => '23:00',
        ]),
    ], outboundVolume: 7000, devices: ['RF' => 24], divisionRules: ['Outbound' => ['minimum_shift' => 2, 'reason' => 'Operational requirement']]);

    $outbound = $result->divisions['Outbound'];
    $dispatch = findActivity($outbound->activities, 'Dispatch');

    expect($dispatch->requiredOneShift)->toBe(2)
        ->and($outbound->recommendedShifts)->toBe(2)
        ->and($outbound->minimumShift)->toBe(2)
        ->and($outbound->shift1)->toBe([])
        ->and($outbound->shift2[0]->name)->toBe('Dispatch')
        ->and($outbound->shift2[0]->mpp)->toBe(2);
});

test('division minimum shift rule forces 2 shifts regardless of workload', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Outbound',
            'name' => 'Picker',
            'workload_source' => 'Outbound',
            'workload_unit' => 'PCS',
            'productivity_per_hour' => 150,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 150,
            'device_type' => 'RF',
        ]),
    ], outboundVolume: 100, devices: ['RF' => 24], divisionRules: ['Outbound' => ['minimum_shift' => 2, 'reason' => 'Operational requirement']]);

    $outbound = $result->divisions['Outbound'];

    expect($outbound->recommendedShifts)->toBe(2)
        ->and($outbound->minimumShift)->toBe(2)
        ->and($outbound->reason)->toBe('Operational requirement');
});

test('shared activity splits workload across both shifts', function () {
    $result = runPlan([
        mpActivity([
            'division' => 'Outbound',
            'name' => 'Picker',
            'workload_source' => 'Outbound',
            'workload_unit' => 'PCS',
            'productivity_per_hour' => 100,
            'productivity_unit' => 'pcs/hour',
            'available_manpower' => 50,
            'device_type' => 'RF',
        ]),
    ], outboundVolume: 28000, devices: ['RF' => 24], divisionRules: ['Outbound' => ['minimum_shift' => 2, 'reason' => null]]);

    $outbound = $result->divisions['Outbound'];

    expect($outbound->shift1[0]->mpp)->toBe(20)
        ->and($outbound->shift2[0]->mpp)->toBe(20)
        ->and($outbound->totalMpp)->toBe(40);
});
