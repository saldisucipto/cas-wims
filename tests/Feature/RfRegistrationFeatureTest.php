<?php

use App\Models\RfDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rf handheld registration page only shows available rf devices', function () {
    RfDevice::query()->create(['code' => 'RF-001', 'status' => 'Available']);
    RfDevice::query()->create(['code' => 'RF-002', 'status' => 'In Use']);
    RfDevice::query()->create(['code' => 'RF-003', 'status' => 'Maintenance']);

    $this->get(route('rf.registration'))
        ->assertSuccessful()
        ->assertSee('RF-001')
        ->assertDontSee('RF-002')
        ->assertDontSee('RF-003');
});
