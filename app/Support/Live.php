<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Throwable;

/**
 * Says that something changed, so every open page can catch up by itself.
 *
 * Changes arrive from two directions — the office, through the web pages, and
 * the site, through the kiosk's API — and both go through Eloquent. So rather
 * than each controller remembering to announce itself, this listens to every
 * create, update, delete and restore the way the Audit Log does, and files the
 * model under the topics a page might be showing: an attendance row is
 * "attendance" and "payroll", a cash advance is "advances" and "payroll".
 *
 * All that is stored is a number per topic, one higher than it was. A page
 * that sees a topic's number move asks the server for its own current
 * contents and patches in what differs, so the feed never has to describe a
 * change — only that there was one. That keeps the kiosk's thirty-second
 * heartbeat from writing a stream of data nobody reads, and keeps this table
 * at a dozen short rows that are read once a second.
 *
 * Everything in here is survivable. A live update is a convenience, and it
 * must never be the reason a save fails or a page 500s: before the table
 * exists — a deploy is live for the minutes before its migration is run — the
 * feed is simply silent, and the pages stay as they load.
 */
final class Live
{
    /** Every topic a page may watch. Anything else is ignored. */
    public const TOPICS = [
        'employees',     // the workforce: added, edited, archived, restored
        'attendance',    // clock-ins and clock-outs, from the kiosk or the web
        'leave',         // leave requests
        'advances',      // cash advances and their payments
        'payroll',       // anything that changes what a payroll comes to
        'settings',      // payroll settings, rates, holidays, system settings
        'sites',         // sites and projects
        'assignments',   // who is posted where
        'devices',       // kiosks: last seen, which site they are set to
        'kiosk',         // the kiosk's own live position, which is cached, not stored
        'accounts',      // logins and roles
        'audit',         // the audit log
        'notifications', // the bell
    ];

    /**
     * The topics each model belongs to.
     *
     * A model missing from here changes nothing anybody watches — the chat
     * assistant's messages, a payroll run's per-worker lines — and is ignored.
     */
    private const MODELS = [
        'Attendance'           => ['attendance', 'payroll'],
        'Employee'             => ['employees', 'attendance', 'payroll', 'assignments'],
        'LeaveRequest'         => ['leave', 'payroll'],
        'Loan'                 => ['advances', 'payroll'],
        'LoanDeduction'        => ['advances', 'payroll'],
        'ValeAdvance'          => ['advances', 'payroll'],
        'Payroll'              => ['payroll'],
        'PayrollRun'           => ['payroll'],
        'PayrollRemittance'    => ['payroll'],
        'Bonus'                => ['payroll', 'settings'],
        'PayrollRate'          => ['payroll', 'settings'],
        'Setting'              => ['payroll', 'settings'],
        'DeductionType'        => ['payroll', 'settings'],
        'LaborType'            => ['payroll', 'settings', 'employees'],
        'Shift'                => ['payroll', 'settings', 'attendance'],
        'Holiday'              => ['payroll', 'settings'],
        'SystemSetting'        => ['settings'],
        'Site'                 => ['sites', 'attendance', 'devices'],
        'Project'              => ['sites', 'assignments'],
        'ProjectAssignment'    => ['assignments', 'employees'],
        'Kiosk'                => ['devices'],
        'User'                 => ['accounts'],
        'AuditLog'             => ['audit'],
        'DatabaseNotification' => ['notifications'],
    ];

    /**
     * What a write request changes when it leaves no model event behind.
     *
     * A bulk delete is one query, and a query is not a model: nothing is
     * created, updated or deleted as far as Eloquent is concerned, so nothing
     * would be announced and forty rows would vanish from a page only on its
     * next load. The route says what it touched instead. Keyed by the first
     * part of the route name.
     */
    private const ROUTES = [
        'employees'          => ['employees', 'attendance', 'payroll'],
        'attendance'         => ['attendance', 'payroll'],
        'leave'              => ['leave', 'payroll'],
        'loans'              => ['advances', 'payroll'],
        'vale-advances'      => ['advances', 'payroll'],
        'payroll'            => ['payroll'],
        'payroll-records'    => ['payroll'],
        'payroll-processing' => ['payroll'],
        'payroll-rates'      => ['payroll', 'settings'],
        'bonus-grants'       => ['payroll', 'settings'],
        'labor-types'        => ['payroll', 'settings', 'employees'],
        'holidays'           => ['payroll', 'settings'],
        'settings'           => ['payroll', 'settings'],
        'system-settings'    => ['settings'],
        'sites'              => ['sites', 'attendance'],
        'assignments'        => ['assignments', 'employees'],
        'accounts'           => ['accounts'],
        'users-roles'        => ['accounts'],
        'notifications'      => ['notifications'],
    ];

    /** True between the first line of a request and its last. */
    private bool $inRequest = false;

    /** Topics already announced during this request. */
    private array $announced = [];

    /**
     * Whether this request wrote a model of its own.
     *
     * Not counting the audit entry, which every write request leaves behind:
     * the question this answers is whether the route did its work through
     * Eloquent or through a query, and an audit line is neither.
     */
    private bool $wroteModel = false;

    /** When the table was last found missing; retried a minute later. */
    private static ?float $silencedAt = null;

    /** Wire the listeners. Called once from AppServiceProvider::boot(). */
    public static function listen(): void
    {
        $me = fn (): self => app(self::class);

        Event::listen(RouteMatched::class, fn (RouteMatched $e) => $me()->begin($e->request));
        Event::listen(RequestHandled::class, fn (RequestHandled $e) => $me()->finish($e->request, $e->response));

        foreach (['created', 'updated', 'deleted', 'restored'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $event, array $data) use ($me) {
                $me()->model($data[0] ?? null);
            });
        }
    }

    // ── Request lifecycle ───────────────────────────────────────────────────

    /**
     * One request that writes forty payroll rows is one announcement per
     * topic, not forty: the page that hears it re-reads itself either way.
     */
    public function begin(Request $request): void
    {
        $this->inRequest  = true;
        $this->announced  = [];
        $this->wroteModel = false;
    }

    public function finish(?Request $request = null, $response = null): void
    {
        try {
            // A write that went through without a single model event is a
            // query that did the work itself. Say what its route touched, so
            // "delete all attendance history" reaches the open pages too.
            if ($this->inRequest && ! $this->wroteModel && $request) {
                $this->fromRoute($request, $response);
            }
        } catch (Throwable $e) {
            report($e);
        } finally {
            $this->inRequest  = false;
            $this->announced  = [];
            $this->wroteModel = false;
        }
    }

    private function fromRoute(Request $request, $response): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $status = is_object($response) && method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

        if ($status >= 400) {
            return;
        }

        // The kiosk: a worker registered, a finger enrolled, a shift clocked.
        if ($request->is('api/kiosk/*')) {
            $this->announce(['attendance', 'employees', 'devices']);

            return;
        }

        $name = $request->route()?->getName();

        if (! $name) {
            return;
        }

        $topics = self::ROUTES[explode('.', $name)[0]] ?? null;

        if ($topics) {
            $this->announce($topics);
        }
    }

    // ── Announcing ──────────────────────────────────────────────────────────

    /** Say that these topics have changed. Unknown topics are ignored. */
    public static function bump(string ...$topics): void
    {
        app(self::class)->announce($topics);
    }

    /** A model was written: announce whatever it belongs to. */
    public function model($model): void
    {
        if (! $model instanceof Model) {
            return;
        }

        $type = class_basename($model);

        // The audit entry is the request's shadow, not its work: a bulk delete
        // leaves one behind having written nothing else, and still needs the
        // route to say what it touched.
        if ($type !== 'AuditLog') {
            $this->wroteModel = true;
        }

        if ($topics = self::MODELS[$type] ?? null) {
            $this->announce($topics);
        }
    }

    /** @param  list<string>  $topics */
    public function announce(array $topics): void
    {
        $topics = array_values(array_intersect($topics, self::TOPICS));

        if ($this->inRequest) {
            $topics = array_values(array_diff($topics, array_keys($this->announced)));

            foreach ($topics as $topic) {
                $this->announced[$topic] = true;
            }
        }

        if ($topics) {
            $this->write($topics);
        }
    }

    /** @param  list<string>  $topics */
    private function write(array $topics): void
    {
        if (self::silenced()) {
            return;
        }

        try {
            $now = now();

            foreach ($topics as $topic) {
                $moved = DB::table('live_changes')
                    ->where('topic', $topic)
                    ->update(['revision' => DB::raw('revision + 1'), 'changed_at' => $now]);

                if (! $moved) {
                    DB::table('live_changes')->insertOrIgnore([
                        'topic' => $topic, 'revision' => 1, 'changed_at' => $now,
                    ]);
                }
            }
        } catch (Throwable $e) {
            self::silence($e);
        }
    }

    // ── Reading ─────────────────────────────────────────────────────────────

    /**
     * Where every topic stands: topic => revision. A topic nothing has ever
     * touched is absent, which reads the same as nought.
     *
     * @return array<string, int>
     */
    public static function revisions(): array
    {
        if (self::silenced()) {
            return [];
        }

        try {
            return DB::table('live_changes')
                ->pluck('revision', 'topic')
                ->map(fn ($r) => (int) $r)
                ->all();
        } catch (Throwable $e) {
            self::silence($e);

            return [];
        }
    }

    /** Whether the feed is readable at all — false while the table is missing. */
    public static function available(): bool
    {
        return ! self::silenced();
    }

    // ── Surviving a missing table ───────────────────────────────────────────

    /**
     * A deploy is live for the minutes before its migration is run by hand, so
     * every read and write here has to survive the table not being there yet.
     * The first failure is reported and then everything goes quiet for a
     * minute, rather than reporting once per save for as long as it lasts.
     */
    private static function silence(Throwable $e): void
    {
        if (self::$silencedAt === null) {
            report($e);
        }

        self::$silencedAt = microtime(true);
    }

    private static function silenced(): bool
    {
        if (self::$silencedAt === null) {
            return false;
        }

        if (microtime(true) - self::$silencedAt < 60) {
            return true;
        }

        self::$silencedAt = null;

        return false;
    }

    /** For tests, which build the table and then expect it to be read. */
    public static function listenAgain(): void
    {
        self::$silencedAt = null;
    }
}
