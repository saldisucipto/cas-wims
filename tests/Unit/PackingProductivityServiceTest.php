<?php

use App\Services\Import\SpreadsheetReader;
use App\Services\PackingProductivity\PackingProductivityService;
use Carbon\Carbon;

function makeService(): PackingProductivityService
{
    return new PackingProductivityService(new SpreadsheetReader);
}

test('estimated active minutes subtracts gaps beyond the inactivity threshold', function () {
    $timestamps = collect([
        Carbon::parse('2026-08-15 08:00:00'),
        Carbon::parse('2026-08-15 08:10:00'),
        Carbon::parse('2026-08-15 09:00:00'), // 50 min gap -> exceeds 30
        Carbon::parse('2026-08-15 09:20:00'),
        Carbon::parse('2026-08-15 09:25:00'),
    ]);

    // Span = 85 min. Gaps: 10, 50 (exceeds), 20, 5 -> subtract 50 => 35 active minutes.
    expect(makeService()->estimatedActiveMinutes($timestamps, 30))->toBe(35);
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

    // Span 30 min, no gap > 30 => 30 active minutes.
    expect(makeService()->estimatedActiveMinutes($timestamps, 30))->toBe(30);
});
