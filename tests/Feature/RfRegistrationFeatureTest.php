<?php

use App\Models\DailyWorker;
use App\Models\RfDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rfWorker(string $name = 'RF Worker', string $code = 'RF-EMP-1'): DailyWorker
{
    return DailyWorker::query()->create([
        'employee_code' => $code,
        'name' => $name,
        'function' => 'Outbound',
        'division' => 'Outbound',
        'position' => 'Picker',
        'status' => 'Active',
        'is_active' => true,
    ]);
}

test('rf handheld registration page lists available workers', function () {
    rfWorker('Ready Worker', 'EMP-RF-1');

    $this->get(route('rf.registration'))
        ->assertSuccessful()
        ->assertSee('Ready Worker');
});

test('rf device field is read only and unassigned until the system recognises the handheld', function () {
    RfDevice::query()->create(['code' => 'RF-007', 'status' => 'Available']);

    $this->get(route('rf.registration'))
        ->assertSuccessful()
        ->assertSee('id="rfDevice" type="text" readonly value="" data-device-status="unassigned"', false)
        ->assertSee('name="rf_code"', false);
});

test('unregistered rf code is rejected', function () {
    RfDevice::query()->create(['code' => 'RF-007', 'status' => 'Available']);

    $this->post(route('rf.registration.device'), ['rf_code' => 'RF-999'])
        ->assertRedirect(route('rf.registration'))
        ->assertSessionHas('error');
});

test('device that is not available cannot be recognised', function () {
    RfDevice::query()->create(['code' => 'RF-009', 'status' => 'In Use']);

    $this->post(route('rf.registration.device'), ['rf_code' => 'RF-009'])
        ->assertRedirect(route('rf.registration'))
        ->assertSessionHas('error');
});

test('system recognises the handheld device and displays it read only', function () {
    RfDevice::query()->create(['code' => 'RF-007', 'status' => 'Available']);

    $this->withSession(['rf.device_code' => 'RF-007'])
        ->get(route('rf.registration'))
        ->assertSuccessful()
        ->assertSee('id="rfDevice" type="text" readonly value="RF-007" data-device-status="available"', false)
        ->assertSee('Perangkat dikenali otomatis oleh sistem')
        ->assertDontSee('name="rf_code"', false);
});

test('recognised device that became unavailable is reported as unavailable', function () {
    RfDevice::query()->create(['code' => 'RF-011', 'status' => 'Maintenance']);

    $this->withSession(['rf.device_code' => 'RF-011'])
        ->get(route('rf.registration'))
        ->assertSuccessful()
        ->assertSee('data-device-status="unavailable"', false)
        ->assertSee('sedang tidak tersedia');
});

test('dashboard uses the recognised handheld instead of a request parameter', function () {
    $this->withSession(['rf.device_code' => 'RF-404'])
        ->get(route('rf.dashboard', ['name' => 'Nobody Test', 'device' => 'RF-999']))
        ->assertRedirect(route('rf.registration'));
});

test('dashboard without a recognised device redirects to registration', function () {
    $this->get(route('rf.dashboard', ['name' => 'Nobody Test']))
        ->assertRedirect(route('rf.registration'))
        ->assertSessionHas('error');
});
