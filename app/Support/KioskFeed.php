<?php

namespace App\Support;

use App\Models\Kiosk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * What just happened at each kiosk, for the live monitor in System Settings.
 *
 * Every scan the kiosk sends passes through the web: a time in or out, a scan
 * turned away, a finger nobody knows. RecordKioskEvent writes each one here as
 * it is answered, and the monitor reads them back within a couple of seconds —
 * so the office sees the scan as the site does, without anything added to the
 * kiosk itself. The last few are kept, in the cache, for a day.
 */
class KioskFeed
{
    private const KEY  = 'kiosk_feed_';
    private const SEQ  = 'kiosk_feed_seq';
    private const KEEP = 30;

    /** Add one event to a kiosk's feed. */
    public static function push(Kiosk $kiosk, array $event): void
    {
        // Some cache stores will not increment a key that is not there yet.
        $seq = (int) Cache::increment(self::SEQ);
        if ($seq < 1) {
            Cache::forever(self::SEQ, $seq = 1);
        }

        $now  = now();
        $list = Cache::get(self::KEY . $kiosk->id, []);
        $list[] = $event + [
            'seq'  => $seq,
            'at'   => $now->toIso8601String(),
            'time' => $now->format('g:i:s A'),
        ];

        Cache::put(self::KEY . $kiosk->id, array_slice($list, -self::KEEP), $now->copy()->addDay());
    }

    /** A kiosk's events after $since, oldest first. */
    public static function since(Kiosk $kiosk, int $since = 0): array
    {
        return array_values(array_filter(
            Cache::get(self::KEY . $kiosk->id, []),
            fn ($e) => $e['seq'] > $since
        ));
    }

    /** The newest event number, so a monitor opening now skips the old ones. */
    public static function last(Kiosk $kiosk): int
    {
        $list = Cache::get(self::KEY . $kiosk->id, []);

        return $list ? (int) end($list)['seq'] : 0;
    }

    /** When the event happened, read back as a Carbon. */
    public static function when(array $event): Carbon
    {
        return Carbon::parse($event['at']);
    }
}
