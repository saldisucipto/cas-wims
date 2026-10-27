<?php

use App\Services\Import\SpreadsheetReader;
use App\Services\PackingProductivity\PackingProductivityService;
use Carbon\Carbon;

function makeService(): PackingProductivityService
{
    return new PackingProductivityService(new SpreadsheetReader);
}

test('estimated active minutes caps each gap at the inactivity threshold', function () {
    $timestamps = collect([
        Carbon::parse('2026-08-15 08:00:00'),
        Carbon::parse('2026-08-15 08:10:00'),
        Carbon::parse('2026-08-15 09:00:00'), // 50 min gap -> capped at 30
        Carbon::parse('2026-08-15 09:20:00'),
        Carbon::parse('2026-08-15 09:25:00'),
    ]);

    // Gaps: 10, 50 (capped to 30), 20, 5 -> 65 active minutes.
    expect(makeService()->estimatedActiveMinutes($timestamps, 30))->toBe(65);
});

test('estimated active minutes returns zero for fewer than two timestamps', function () {
    expect(makeService()->estimatedActiveMinutes(collect(), 30))->toBe(0)
        ->and(makeService()->estimatedActiveMinutes(collect([Carbon::parse('2026-08-15 08:00:00')]), 30))->toBe(0);
});

test('estimated active minutes treats gaps within threshold as active', function () {
    $timestamps = collect([
        Carbon::parse('2026-08-15 08:00:00'),
        Carbon::parse('2026-08-15 08:15:00'),
        Carbon::parse('2026-08-15 08:30:00'),
    ]);

    // No gap exceeds 30 minutes => 30 active minutes.
    expect(makeService()->estimatedActiveMinutes($timestamps, 30))->toBe(30);
});

test('estimated active minutes never collapses a sparse worker to zero', function () {
    // Two transactions 108 minutes apart still represent capped working time,
    // not a session with zero productivity.
    $timestamps = collect([
        Carbon::parse('2026-08-15 08:40:20'),
        Carbon::parse('2026-08-15 10:28:36'),
    ]);

    expect(makeService()->estimatedActiveMinutes($timestamps, 30))->toBe(30);
});

test('estimated active minutes sums a dense burst of transactions', function () {
    $timestamps = collect([
        Carbon::parse('2026-08-15 08:00:00'),
        Carbon::parse('2026-08-15 08:05:00'),
        Carbon::parse('2026-08-15 08:10:00'),
    ]);

    expect(makeService()->estimatedActiveMinutes($timestamps, 30))->toBe(10);
});
