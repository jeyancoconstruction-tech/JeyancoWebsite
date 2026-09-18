<?php

namespace App\Http\Controllers;

use App\Support\Live;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The connection a page keeps open so it never has to be refreshed by hand.
 *
 * Server-Sent Events rather than WebSockets: this app is served by one PHP
 * container with no second process to run, and a one-way feed is all a page
 * needs — every change the browser makes goes back over an ordinary form post,
 * and everything it needs to hear about comes down this. The browser's own
 * EventSource reconnects when the line drops, which covers a site losing its
 * connection, a laptop waking up, and a deploy restarting the container.
 *
 * What comes down it is deliberately thin: the topics that changed and their
 * revision numbers, never the data itself. The page then asks for its own
 * current contents and patches in what differs. A kiosk filing attendance for
 * forty workers is one line down the wire, and a page showing none of them
 * fetches nothing at all.
 *
 * An open stream holds a PHP worker, so the number open at once is capped
 * (config/live.php). A tab past the cap is told to ask "what has changed?"
 * every few seconds instead — still no page reloads, still no data fetched
 * until something actually moves.
 */
class LiveController extends Controller
{
    /** How long a browser waits before reconnecting, in milliseconds. */
    private const RETRY_MS = 3000;

    /** Nothing said for this long and a comment goes out to hold the line open. */
    private const HEARTBEAT_SECONDS = 15;

    /**
     * GET /live/revisions — where every topic stands, in one short answer.
     *
     * This is what a tab without a stream asks for, and what every tab asks
     * for the moment it becomes visible again.
     */
    public function revisions()
    {
        return response()->json([
            'revisions' => Live::revisions(),
            'stream'    => (bool) config('live.stream', true) && Live::available(),
            'poll_ms'   => (int) config('live.poll_ms', 8000),
            'at'        => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    /** GET /live/stream — the open connection itself. */
    public function stream(Request $request): StreamedResponse
    {
        $seconds = max(0, (int) config('live.seconds', 30));
        $tick    = max(100, (int) config('live.tick_ms', 1000)) * 1000;   // microseconds
        $once    = $request->boolean('once');

        // What the page already knows, as "topic:revision,topic:revision", so
        // anything that changed while it was reconnecting is sent at once
        // rather than waiting for the next change after it.
        $known = $this->known((string) $request->query('since', ''));

        $slot = $this->claimSlot();

        return response()->stream(function () use ($seconds, $tick, $once, $known, $slot) {
            $flush = ! app()->runningUnitTests();

            if ($flush) {
                @set_time_limit($seconds + 15);
                ignore_user_abort(false);
            }

            $say = function (string $event, array $data) use ($flush) {
                echo 'event: ' . $event . "\n";
                echo 'data: ' . json_encode($data) . "\n\n";

                if ($flush) {
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                }
            };

            echo 'retry: ' . self::RETRY_MS . "\n\n";

            // No slot free: the workers that are left are for pages to load
            // with. The tab asks what changed every few seconds instead, and
            // tries for a stream again after a while.
            if ($slot === null) {
                $say('poll', [
                    'poll_ms' => (int) config('live.poll_ms', 8000),
                    'retry_in' => 60,
                    'reason'  => 'busy',
                ]);

                return;
            }

            $sent = Live::revisions();

            $say('hello', ['revisions' => $sent, 'poll_ms' => (int) config('live.poll_ms', 8000)]);

            // Changes it missed between one stream closing and this one
            // opening — a gap of a few seconds every time, and the one moment
            // a live page could otherwise sit on stale figures for good.
            if ($missed = $this->diff($known, $sent)) {
                $say('change', ['topics' => $missed]);
            }

            try {
                $deadline = microtime(true) + $seconds;
                $spoke    = microtime(true);

                while (! $once && microtime(true) < $deadline) {
                    usleep($tick);

                    if (connection_aborted()) {
                        break;
                    }

                    $now = Live::revisions();

                    if ($changed = $this->diff($sent, $now)) {
                        $sent  = $now;
                        $spoke = microtime(true);
                        $say('change', ['topics' => $changed]);

                        continue;
                    }

                    if (microtime(true) - $spoke >= self::HEARTBEAT_SECONDS) {
                        $spoke = microtime(true);
                        $say('ping', ['at' => now()->toIso8601String()]);
                    }
                }

                // Said plainly so the page knows this was the stream's turn
                // ending rather than the connection failing, and can open the
                // next one without counting it as a drop-out.
                $say('bye', ['reason' => 'cycle']);
            } catch (Throwable $e) {
                report($e);
            } finally {
                $this->releaseSlot($slot);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, private',
            'Connection'        => 'keep-alive',
            // Nginx and Caddy buffer a response by default, which would hold
            // every line back until the stream closed — the whole point lost.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // ── The shape of what is sent ───────────────────────────────────────────

    /**
     * The topics whose revision moved, as topic => revision.
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return array<string, int>
     */
    private function diff(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $topic => $revision) {
            if (($before[$topic] ?? 0) !== $revision) {
                $changed[$topic] = $revision;
            }
        }

        return $changed;
    }

    /**
     * "attendance:12,payroll:3" as the page last saw it.
     *
     * @return array<string, int>
     */
    private function known(string $since): array
    {
        $from = [];

        foreach (explode(',', $since) as $pair) {
            [$topic, $revision] = array_pad(explode(':', $pair, 2), 2, null);

            if ($topic !== '' && $revision !== null && ctype_digit($revision) && in_array($topic, Live::TOPICS, true)) {
                $from[$topic] = (int) $revision;
            }
        }

        return $from;
    }

    // ── Keeping the container on its feet ───────────────────────────────────

    /**
     * Take one of the places for an open stream, or nothing if they are taken.
     *
     * Each place is a cache key that expires on its own, so a stream cut off
     * mid-flight — a closed laptop, a killed container — gives its place back
     * shortly afterwards without anything having to notice it went.
     */
    private function claimSlot(): ?string
    {
        $max = max(0, (int) config('live.max_streams', 4));

        if ($max === 0 || ! config('live.stream', true) || ! Live::available()) {
            return null;
        }

        $ttl = max(5, (int) config('live.seconds', 30)) + 10;

        for ($i = 1; $i <= $max; $i++) {
            $key = 'live:stream:' . $i;

            try {
                if (Cache::add($key, true, $ttl)) {
                    return $key;
                }
            } catch (Throwable) {
                // No cache store to speak of. Rather than refuse every stream,
                // let it through: the cap is a safety net, not a gate.
                return 'live:stream:uncounted';
            }
        }

        return null;
    }

    private function releaseSlot(?string $slot): void
    {
        if ($slot === null || $slot === 'live:stream:uncounted') {
            return;
        }

        try {
            Cache::forget($slot);
        } catch (Throwable) {
            // It expires by itself.
        }
    }
}
