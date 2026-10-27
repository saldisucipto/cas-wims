<?php

namespace App\Services\PackingProductivity;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Bridges the two clock domains used by the packing module.
 *
 * - `working_sessions` timestamps are written with `now()`, so they hold real
 *   instants (application timezone, UTC in this deployment).
 * - `meson_transactions` timestamps are copied verbatim from the Meson export,
 *   so they hold warehouse wall-clock time stored without any timezone.
 *
 * Every comparison between the two must go through this class, otherwise the
 * offset shifts transactions out of their session window.
 */
final class OperationalClock
{
    private const DEFAULT_TIMEZONE = 'Asia/Jakarta';

    private static ?string $cachedTimezone = null;

    public static function timezone(): string
    {
        if (self::$cachedTimezone !== null) {
            return self::$cachedTimezone;
        }

        $configured = SystemSetting::query()
            ->where('setting_key', 'pp_timezone')
            ->value('setting_value');

        return self::$cachedTimezone = is_string($configured) && $configured !== ''
            ? $configured
            : self::DEFAULT_TIMEZONE;
    }

    /**
     * Convert a real instant into the naive wall-clock representation used by
     * the Meson export, so it can be compared against `transaction_time`.
     */
    public static function toWallClock(?CarbonInterface $instant): ?Carbon
    {
        if ($instant === null) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            Carbon::instance($instant)->setTimezone(self::timezone())->format('Y-m-d H:i:s'),
        );
    }

    public static function now(): Carbon
    {
        return self::toWallClock(now()) ?? now();
    }

    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    /**
     * Convert a naive Meson wall-clock value back into a real instant.
     */
    public static function fromWallClock(?CarbonInterface $wallClock): ?Carbon
    {
        if ($wallClock === null) {
            return null;
        }

        return Carbon::createFromFormat(
            'Y-m-d H:i:s',
            Carbon::instance($wallClock)->format('Y-m-d H:i:s'),
            self::timezone(),
        )->setTimezone(config('app.timezone') ?: 'UTC');
    }

    public static function flush(): void
    {
        self::$cachedTimezone = null;
    }
}
