<?php

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('administrator can view atk administration pages', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);

    $this->actingAs($administrator)
        ->get(route('administration.dashboard'))
        ->assertSuccessful()
        ->assertSee('Pending ATK Approvals');

    $this->actingAs($administrator)
        ->get(route('administration.master.atk'))
        ->assertSuccessful()
        ->assertSee('Master ATK');

    $this->actingAs($administrator)
        ->get(route('administration.inventory.atk-receiving'))
        ->assertSuccessful()
        ->assertSee('Penerimaan ATK');

    $this->actingAs($administrator)
        ->get(route('administration.reports.atk-stock-card'))
        ->assertSuccessful()
        ->assertSee('Kartu Stok ATK');

    $this->actingAs($administrator)
        ->get(route('atk.take'))
        ->assertSuccessful()
        ->assertSee('Pengambilan ATK');
});

test('leader can view atk request page', function () {
    $leader = User::factory()->create(['role' => 'Leader']);

    $this->actingAs($leader)
        ->get(route('atk.requests'))
        ->assertSuccessful()
        ->assertSee('Permintaan ATK')
        ->assertSee('Request History');
});

test('leader can submit atk request', function () {
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-100',
        'name' => 'Pulpen Test',
        'category' => 'Writing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 30,
        'status' => 'Active',
        'notes' => 'Test item',
    ]);

    $response = $this
        ->actingAs($leader)
        ->from(route('atk.requests'))
        ->post(route('atk.requests.store'), [
            'notes' => 'Need supplies for coordination meeting.',
            'items' => [
                [
                    'atk_item_id' => $atkItem->id,
                    'quantity' => 4,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('atk.requests'))
        ->assertSessionHas('success', 'ATK request submitted.');

    $request = AtkRequest::query()->first();

    expect($request)->not->toBeNull();

    $this->assertDatabaseHas('atk_requests', [
        'id' => $request->id,
        'requested_by' => $leader->id,
        'status' => 'Pending',
        'notes' => 'Need supplies for coordination meeting.',
    ]);

    $this->assertDatabaseHas('atk_request_items', [
        'atk_request_id' => $request->id,
        'atk_item_id' => $atkItem->id,
        'quantity' => 4,
    ]);
});

test('guest cannot access atk request page', function () {
    $this->get(route('atk.requests'))
        ->assertRedirect(route('administration.login'));
});

test('guest cannot access atk take page', function () {
    $this->get(route('atk.take'))
        ->assertRedirect(route('administration.login'));
});

test('administrator approval reduces atk stock and records stock card history', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-101',
        'name' => 'Kertas A4 Test',
        'category' => 'Paper',
        'unit' => 'Rim',
        'minimum_stock' => 5,
        'current_stock' => 20,
        'status' => 'Active',
        'notes' => null,
    ]);

    $atkRequest = AtkRequest::query()->create([
        'request_number' => 'ATK-REQ-777',
        'requested_by' => $leader->id,
        'notes' => 'Need paper for documents.',
        'status' => 'Pending',
        'requested_at' => now(),
    ]);

    $atkRequest->items()->create([
        'atk_item_id' => $atkItem->id,
        'quantity' => 4,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->from(route('administration.dashboard'))
        ->post(route('administration.atk-requests.approve', $atkRequest));

    $response
        ->assertRedirect(route('administration.dashboard'))
        ->assertSessionHas('success', 'ATK request approved.');

    $this->assertDatabaseHas('atk_items', [
        'id' => $atkItem->id,
        'current_stock' => 16,
    ]);

    $this->assertDatabaseHas('atk_requests', [
        'id' => $atkRequest->id,
        'status' => 'Approved',
        'approved_by' => $administrator->id,
    ]);

    $this->assertDatabaseHas('atk_stock_transactions', [
        'atk_item_id' => $atkItem->id,
        'transaction_type' => 'Approval',
        'reference' => 'ATK-REQ-777',
        'quantity_in' => 0,
        'quantity_out' => 4,
        'balance' => 16,
        'performed_by' => $administrator->id,
    ]);
});

test('administrator rejection keeps stock unchanged and saves rejection notes', function () {
    $administrator = User::factory()->create(['role' => 'Administrator']);
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-102',
        'name' => 'Sticky Notes Test',
        'category' => 'Paper',
        'unit' => 'Pack',
        'minimum_stock' => 5,
        'current_stock' => 12,
        'status' => 'Active',
        'notes' => null,
    ]);

    $atkRequest = AtkRequest::query()->create([
        'request_number' => 'ATK-REQ-778',
        'requested_by' => $leader->id,
        'notes' => 'Need extra sticky notes.',
        'status' => 'Pending',
        'requested_at' => now(),
    ]);

    $atkRequest->items()->create([
        'atk_item_id' => $atkItem->id,
        'quantity' => 2,
    ]);

    $response = $this
        ->actingAs($administrator)
        ->from(route('administration.dashboard'))
        ->post(route('administration.atk-requests.reject', $atkRequest), [
            'rejection_notes' => 'Use remaining stock first.',
        ]);

    $response
        ->assertRedirect(route('administration.dashboard'))
        ->assertSessionHas('success', 'ATK request rejected.');

    $this->assertDatabaseHas('atk_items', [
        'id' => $atkItem->id,
        'current_stock' => 12,
    ]);

    $this->assertDatabaseHas('atk_requests', [
        'id' => $atkRequest->id,
        'status' => 'Rejected',
        'rejected_by' => $administrator->id,
        'rejection_notes' => 'Use remaining stock first.',
    ]);

    $this->assertDatabaseMissing('atk_stock_transactions', [
        'reference' => 'ATK-REQ-778',
    ]);
});

test('leader can take atk directly and transaction records performer', function () {
    $leader = User::factory()->create(['role' => 'Leader']);
    $atkItem = AtkItem::query()->create([
        'code' => 'ATK-200',
        'name' => 'Map Folder Test',
        'category' => 'Filing',
        'unit' => 'Pcs',
        'minimum_stock' => 5,
        'current_stock' => 25,
        'status' => 'Active',
        'notes' => null,
    ]);

    $response = $this
        ->actingAs($leader)
        ->from(route('atk.take'))
        ->post(route('atk.take.store'), [
            'transaction_date' => '2026-08-11',
            'notes' => 'Dipakai untuk dokumen outgoing.',
            'items' => [
                [
                    'atk_item_id' => $atkItem->id,
                    'quantity' => 4,
                ],
            ],
        ]);

    $response
        ->assertRedirect(route('atk.take'))
        ->assertSessionHas('success', 'ATK direct take posted.');

    $this->assertDatabaseHas('atk_items', [
        'id' => $atkItem->id,
        'current_stock' => 21,
    ]);

    $this->assertDatabaseHas('atk_stock_transactions', [
        'atk_item_id' => $atkItem->id,
        'transaction_type' => 'Direct Take',
        'reference' => 'Direct ATK Take',
        'quantity_in' => 0,
        'quantity_out' => 4,
        'balance' => 21,
        'performed_by' => $leader->id,
        'notes' => 'Dipakai untuk dokumen outgoing.',
    ]);
});
