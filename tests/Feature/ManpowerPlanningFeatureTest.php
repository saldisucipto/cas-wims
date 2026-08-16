<?php

use App\Models\DeviceAvailability;
use App\Models\ManpowerActivity;
use App\Models\ManpowerPlanning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUnloadingActivity(): ManpowerActivity
{
    return ManpowerActivity::query()->create([
        'division' => 'Inbound',
        'name' => 'Unloading',
        'code' => 'INB-UNLOAD',
        'workload_source' => 'Inbound',
        'workload_unit' => 'PCS',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 800,
        'productivity_unit' => 'pcs/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 20,
    ]);
}

test('manpower planning page requires administrator', function () {
    $this->get(route('administration.manpower-planning'))
        ->assertRedirect(route('administration.login'));
});

test('manpower planning page renders for administrator', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']))
        ->get(route('administration.manpower-planning'))
        ->assertSuccessful()
        ->assertSee('Manpower Planning');
});

test('manpower planning computes and displays results per division', function () {
    ManpowerActivity::query()->create([
        'division' => 'Inbound',
        'name' => 'Unloading',
        'code' => 'INB-UNLOAD',
        'workload_source' => 'Inbound',
        'workload_unit' => 'PCS',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 800,
        'productivity_unit' => 'pcs/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 20,
    ]);

    ManpowerActivity::query()->create([
        'division' => 'Outbound',
        'name' => 'Check-Pack',
        'code' => 'OB-CHECKPACK',
        'workload_source' => 'Outbound',
        'workload_unit' => 'Order',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 24,
        'productivity_unit' => 'order/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 300,
    ]);

    $this->actingAs(User::factory()->create(['role' => 'Administrator']))
        ->get(route('administration.manpower-planning', [
            'inbound_volume' => 10000,
            'outbound_volume' => 5000,
        ]))
        ->assertSuccessful()
        ->assertSee('Unloading')
        ->assertSee('Check-Pack')
        ->assertSee('FEASIBLE');
});

test('administrator can create a manpower activity', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']))
        ->post(route('administration.master.manpower-activities.store'), [
            'division' => 'Outbound',
            'name' => 'Picker',
            'code' => 'OB-PICKER',
            'workload_source' => 'Outbound',
            'workload_unit' => 'PCS',
            'conversion_ratio' => 2.8,
            'productivity_per_hour' => 150,
            'productivity_unit' => 'pcs/hour',
            'manpower_type' => 'Variable',
            'available_manpower' => 150,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('manpower_activities', [
        'code' => 'OB-PICKER',
        'name' => 'Picker',
    ]);
});

test('administrator can save a vas schedule', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']))
        ->post(route('administration.master.manpower-vas-schedules.store'), [
            'schedule_date' => '2026-08-19',
            'volume' => 3000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('manpower_vas_schedules', [
        'schedule_date' => '2026-08-19',
        'volume' => 3000,
    ]);
});

test('administrator can save a planning and it gets a planning number', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    createUnloadingActivity();

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 10000,
        'outbound_volume' => 0,
        'planning_date' => '2026-08-16',
    ])->assertRedirect();

    $planning = ManpowerPlanning::query()->first();

    expect($planning->planning_number)->toStartWith('MP-20260816-')
        ->and($planning->status)->toBe('CALCULATED')
        ->and($planning->inbound_volume)->toBe(10000);

    $this->assertDatabaseHas('manpower_planning_items', [
        'manpower_planning_id' => $planning->id,
        'name' => 'Unloading',
        'required_mpp' => 2,
    ]);
});

test('planning history lists saved plannings', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    ManpowerPlanning::factory()->create([
        'planning_number' => 'MP-20260816-0001',
        'planning_date' => '2026-08-16',
    ]);

    $this->get(route('administration.manpower-planning.history'))
        ->assertSuccessful()
        ->assertSee('MP-20260816-0001');
});

test('planning view shows stored snapshot as a document', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    $planning = ManpowerPlanning::factory()->create(['planning_number' => 'MP-20260816-0001']);
    $planning->items()->create([
        'division' => 'Inbound',
        'name' => 'Unloading',
        'code' => 'INB-UNLOAD',
        'workload_source' => 'Inbound',
        'workload' => 10000,
        'workload_unit' => 'PCS',
        'productivity_per_hour' => 800,
        'productivity_unit' => 'pcs/hour',
        'manpower_type' => 'Variable',
        'effective_working_hours' => 7,
        'required_mpp' => 2,
        'mpp_per_shift' => 1,
        'number_of_shift' => 1,
        'available_mpp' => 20,
        'feasibility_status' => 'Feasible',
        'sort_order' => 1,
    ]);

    $this->get(route('administration.manpower-planning.show', $planning))
        ->assertSuccessful()
        ->assertSee('MP-20260816-0001')
        ->assertSee('Unloading');
});

test('editing a planning recalculates and bumps revision', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    createUnloadingActivity();
    $planning = ManpowerPlanning::factory()->create([
        'inbound_volume' => 10000,
        'status' => 'CALCULATED',
    ]);

    $this->post(route('administration.manpower-planning.update', $planning), [
        'inbound_volume' => 20000,
        'outbound_volume' => 0,
        'planning_date' => '2026-08-16',
    ])->assertRedirect();

    $planning->refresh();

    expect($planning->inbound_volume)->toBe(20000)
        ->and($planning->revision)->toBe(2)
        ->and($planning->items()->first()->required_mpp)->toBe(4);
});

test('duplicate creates a new draft planning without touching original', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    $planning = ManpowerPlanning::factory()->create([
        'planning_number' => 'MP-20260816-0001',
        'status' => 'FINAL',
    ]);

    $this->post(route('administration.manpower-planning.duplicate', $planning))
        ->assertRedirect();

    expect(ManpowerPlanning::query()->count())->toBe(2)
        ->and($planning->refresh()->status)->toBe('FINAL');

    $duplicate = ManpowerPlanning::query()->where('id', '!=', $planning->id)->first();
    expect($duplicate->status)->toBe('DRAFT');
});

test('finalize marks a planning as final', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    $planning = ManpowerPlanning::factory()->create(['status' => 'CALCULATED']);

    $this->post(route('administration.manpower-planning.finalize', $planning))
        ->assertRedirect();

    expect($planning->refresh()->status)->toBe('FINAL');
});

test('saved planning snapshots device availability', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    DeviceAvailability::query()->create(['device_type' => 'PC', 'ready_quantity' => 10]);

    ManpowerActivity::query()->create([
        'division' => 'Inbound',
        'name' => 'Receiving',
        'code' => 'INB-RECEIVE',
        'workload_source' => 'Inbound',
        'workload_unit' => 'PCS',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 400,
        'productivity_unit' => 'pcs/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 40,
        'device_type' => 'PC',
    ]);

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 10000,
        'outbound_volume' => 0,
        'planning_date' => '2026-08-16',
    ]);

    $planning = ManpowerPlanning::query()->first();

    expect($planning->items()->first()->device_type)->toBe('PC');

    $this->assertDatabaseHas('manpower_planning_devices', [
        'manpower_planning_id' => $planning->id,
        'device_type' => 'PC',
        'ready_quantity' => 10,
    ]);
});

test('administrator can manage device availability', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']))
        ->post(route('administration.master.manpower-device-availabilities.store'), [
            'device_type' => 'RF',
            'ready_quantity' => 24,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('device_availabilities', [
        'device_type' => 'RF',
        'ready_quantity' => 24,
    ]);
});

test('updating a planning replaces the device snapshot without duplicates', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    DeviceAvailability::query()->create(['device_type' => 'PC', 'ready_quantity' => 10]);

    ManpowerActivity::query()->create([
        'division' => 'Outbound',
        'name' => 'Check-Pack',
        'code' => 'OB-CHECKPACK',
        'workload_source' => 'Outbound',
        'workload_unit' => 'Order',
        'conversion_ratio' => 1,
        'productivity_per_hour' => 24,
        'productivity_unit' => 'order/hour',
        'manpower_type' => 'Variable',
        'available_manpower' => 300,
        'device_type' => 'PC',
    ]);

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 0,
        'outbound_volume' => 5000,
        'planning_date' => '2026-08-16',
    ]);

    $planning = ManpowerPlanning::query()->first();
    expect($planning->devices()->count())->toBe(1);

    $this->post(route('administration.manpower-planning.update', $planning), [
        'inbound_volume' => 0,
        'outbound_volume' => 8000,
        'planning_date' => '2026-08-16',
    ]);

    expect($planning->devices()->count())->toBe(1);
});

test('saved planning snapshot does not change when master productivity changes', function () {
    $this->actingAs(User::factory()->create(['role' => 'Administrator']));
    $activity = createUnloadingActivity();

    $this->post(route('administration.manpower-planning.store'), [
        'inbound_volume' => 10000,
        'outbound_volume' => 0,
        'planning_date' => '2026-08-16',
    ]);

    $planning = ManpowerPlanning::query()->first();
    $item = $planning->items()->first();

    expect($item->productivity_per_hour)->toBe(800.0)
        ->and($item->required_mpp)->toBe(2);

    $activity->update(['productivity_per_hour' => 900]);

    expect($item->refresh()->productivity_per_hour)->toBe(800.0)
        ->and($item->refresh()->required_mpp)->toBe(2);
});
