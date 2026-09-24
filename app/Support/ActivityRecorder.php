<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the Audit Log for everything a request does, so the log is the whole
 * story and not only the handful of places that remembered to call
 * AuditLog::record().
 *
 * Three sources, one log:
 *
 *   1. Every Eloquent create, update, delete and restore, described field by
 *      field. They are collected while the request runs and written once it
 *      has answered, so a request that fails part-way writes nothing.
 *   2. Sign-in, sign-out, failed sign-in, lockout and password reset, from the
 *      auth events.
 *   3. A write request that changed nothing through a model — a bulk delete
 *      done in one query — is logged by its route, so it is not missing.
 *
 * An explicit AuditLog::record() always wins. A model entry for the record it
 * names — or for its module, when it names none — is dropped as a duplicate of
 * the richer line.
 *
 * Only web and API requests are recorded. Migrations, seeders, and tests that
 * build their data directly write nothing, and any failure in here is
 * reported and swallowed: the log must never be the reason a save fails.
 */
final class ActivityRecorder
{
    /** Never logged: the log itself, the assistant's chat, a run's per-worker lines. */
    private const IGNORED = ['AuditLog', 'ChatMessage', 'PayrollRunItem'];

    /** Columns whose change on its own is bookkeeping rather than an activity. */
    private const NOISE = ['updated_at', 'created_at', 'deleted_at', 'last_seen_at', 'last_login_at', 'remember_token',
        // An account's name in parts: the whole name's change already says it.
        'first_name', 'last_name',
        // Reset when the email changes; the email's own change is the news.
        'google_linked_at'];

    /** Values that are never written into a description, only named. */
    private const SECRET = ['password', 'remember_token'];

    /** The module a model's entries are filed under. */
    private const MODULES = [
        'Attendance'        => 'Attendance',
        'Employee'          => 'Employees',
        'Site'              => 'Sites',
        'Project'           => 'Projects',
        'ProjectAssignment' => 'Assignments',
        'LeaveRequest'      => 'Leave',
        'Loan'              => 'Loans',
        'LoanDeduction'     => 'Loans',
        'ValeAdvance'       => 'Cash Advances',
        'Bonus'             => 'Payroll Settings',
        'DeductionType'     => 'Payroll Settings',
        'LaborType'         => 'Payroll Settings',
        'PayrollRate'       => 'Payroll Settings',
        'Setting'           => 'Payroll Settings',
        'Shift'             => 'Payroll Settings',
        'Holiday'           => 'Holidays',
        'Payroll'           => 'Payroll',
        'PayrollRun'        => 'Payroll',
        'PayrollRemittance' => 'Payroll',
        'Kiosk'             => 'Kiosks',
        'User'              => 'Users',
        'SystemSetting'     => 'Settings',
    ];

    /** How a model is named inside a sentence. */
    private const NOUNS = [
        'User'              => 'account',
        'LeaveRequest'      => 'leave request',
        'ProjectAssignment' => 'assignment',
        'ValeAdvance'       => 'cash advance',
        'PayrollRun'        => 'payroll run',
        'PayrollRemittance' => 'remittance',
        'LoanDeduction'     => 'loan deduction',
        'LaborType'         => 'labor type',
        'DeductionType'     => 'deduction type',
        'PayrollRate'       => 'payroll rates',
        'Setting'           => 'payroll settings',
        'SystemSetting'     => 'system settings',
        'Payroll'           => 'payroll record',
    ];

    /** Routes that change data without a model event, in their own words. */
    private const ROUTES = [
        'attendance.history.bulk-delete' => ['Attendance', 'deleted', 'Deleted selected attendance history'],
        'attendance.history.delete-all'  => ['Attendance', 'deleted', 'Deleted all attendance history'],
        'employees.bulk-delete'          => ['Employees', 'deleted', 'Moved selected employees to Removed'],
        'employees.delete-all'           => ['Employees', 'deleted', 'Moved every employee to Removed'],
        'holidays.bulk-toggle'           => ['Holidays', 'updated', 'Turned holidays on or off in bulk'],
        'holidays.sync'                  => ['Holidays', 'updated', 'Synced holidays from Google Calendar'],
    ];

    /**
     * Write requests that are not activities worth a line of their own, or
     * that the auth events already describe. A trailing dot means "any route
     * under this name".
     */
    private const QUIET = ['login', 'login.post', 'logout', 'password.', 'ai.', 'notifications.', 'search', 'analytics.'];

    private bool $capturing = false;
    private ?Request $request = null;
    private array $pending = [];
    private array $explicitSubjects = [];
    private array $explicitModules = [];
    private int $written = 0;
    private bool $quietLogout = false;
    private array $names = [];

    /** Wire the listeners. Called once from AppServiceProvider::boot(). */
    public static function listen(): void
    {
        $me = fn (): self => app(self::class);

        Event::listen(RouteMatched::class, fn (RouteMatched $e) => $me()->begin($e->request));
        Event::listen(RequestHandled::class, fn (RequestHandled $e) => $me()->finish($e->request, $e->response));

        foreach (['created', 'updated', 'deleted', 'restored'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $event, array $data) use ($me, $action) {
                $me()->model($action, $data[0] ?? null);
            });
        }

        Event::listen(Login::class, fn (Login $e) => $me()->signedIn($e));
        Event::listen(Logout::class, fn (Logout $e) => $me()->signedOut($e));
        Event::listen(Failed::class, fn (Failed $e) => $me()->failed($e));
        Event::listen(Lockout::class, fn (Lockout $e) => $me()->lockedOut($e));
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => $me()->passwordReset($e));
    }

    // ── Request lifecycle ───────────────────────────────────────────────────

    /** A worker process serves many requests: every one starts clean. */
    public function begin(Request $request): void
    {
        $this->capturing        = true;
        $this->request          = $request;
        $this->pending          = [];
        $this->explicitSubjects = [];
        $this->explicitModules  = [];
        $this->written          = 0;
        $this->quietLogout      = false;
        $this->names            = [];
    }

    public function finish(Request $request, $response): void
    {
        if (! $this->capturing || $request !== $this->request) {
            return;
        }
        $this->capturing = false;

        try {
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

            // A request that crashed may have rolled back what it collected.
            if ($status >= 500) {
                return;
            }

            $entries = array_filter($this->pending, fn (array $p) =>
                ! isset($this->explicitSubjects[$p['type'] . '#' . $p['id']])
                && ! isset($this->explicitModules[strtolower($p['module'])]));

            foreach ($this->collapse(array_values($entries)) as $entry) {
                $this->write($entry);
            }

            if ($this->written === 0) {
                $this->fallback($request, $status);
            }
        } catch (Throwable $e) {
            report($e);
        } finally {
            $this->pending = [];
            $this->request = null;
        }
    }

    /** AuditLog::record() tells the recorder what it has already said. */
    public function noteExplicit(string $module, ?Model $subject): void
    {
        $this->written++;

        if ($subject) {
            $this->explicitSubjects[class_basename($subject) . '#' . $subject->getKey()] = true;
        } else {
            $this->explicitModules[strtolower($module)] = true;
        }
    }

    // ── Model events ────────────────────────────────────────────────────────

    public function model(string $action, $model): void
    {
        if (! $this->capturing || ! $model instanceof Model) {
            return;
        }

        try {
            $type = class_basename($model);

            if (! str_starts_with(get_class($model), 'App\\Models\\') || in_array($type, self::IGNORED, true)) {
                return;
            }

            // A restore also saves deleted_at = null, and a heartbeat saves
            // last_seen_at. Neither is a change anybody made.
            if ($action === 'updated' && ! array_diff(array_keys($model->getChanges()), self::NOISE)) {
                return;
            }

            [$verb, $description] = $this->describe($action, $model, $type);

            $this->pending[] = [
                'module'      => self::MODULES[$type] ?? Str::headline(Str::plural($type)),
                'action'      => $verb,
                'type'        => $type,
                'id'          => $model->getKey(),
                'description' => $description,
                'actor'       => $this->actor(),
            ];
        } catch (Throwable $e) {
            report($e);
        }
    }

    // ── Auth events ─────────────────────────────────────────────────────────

    public function signedIn(Login $e): void
    {
        $user = $e->user;

        // AuthController signs a deactivated account straight back out. That
        // is a refused sign-in, not a session that began and ended.
        if (isset($user->is_active) && ! $user->is_active) {
            $this->quietLogout = true;
            $this->authEntry($user->getAuthIdentifier(), $this->nameOf($user), 'blocked', 'A deactivated account tried to sign in');
            return;
        }

        $how = $this->viaGoogle() ? 'Signed in with Google' : 'Signed in';

        $this->authEntry($user->getAuthIdentifier(), $this->nameOf($user), 'signed in', $e->remember ? "{$how} (remember me)" : $how);
    }

    public function signedOut(Logout $e): void
    {
        if ($this->quietLogout) {
            $this->quietLogout = false;
            return;
        }

        if ($e->user) {
            $this->authEntry($e->user->getAuthIdentifier(), $this->nameOf($e->user), 'signed out', 'Signed out');
        }
    }

    public function failed(Failed $e): void
    {
        // Only the identifier — the credentials also carry the password.
        $login = (string) ($e->credentials['username'] ?? $e->credentials['email'] ?? '');

        $this->authEntry($e->user?->getAuthIdentifier(), $e->user ? $this->nameOf($e->user) : ($login ?: 'Unknown'),
            'failed', match (true) {
                $this->viaGoogle() && $e->user !== null => 'Google sign-in refused for “' . Str::limit($login, 60) . '” — this account signs in with a password only',
                $this->viaGoogle()                     => 'Google sign-in refused for “' . Str::limit($login, 60) . '” — no account has that email',
                default                                => 'Failed sign-in for “' . Str::limit($login, 60) . '”',
            });
    }

    /** Whether this request is Google handing a visitor back to sign in. */
    private function viaGoogle(): bool
    {
        return (bool) $this->request?->routeIs('login.google.callback');
    }

    public function lockedOut(Lockout $e): void
    {
        $login = (string) $e->request->input('username', '');

        $this->authEntry(null, $login ?: 'Unknown', 'locked out',
            'Too many failed sign-ins for “' . Str::limit($login, 60) . '” — sign-in paused on that computer');
    }

    public function passwordReset(PasswordReset $e): void
    {
        $this->authEntry($e->user->getAuthIdentifier(), $this->nameOf($e->user), 'password reset',
            'Reset their password from an emailed link');
    }

    // ── Writing ─────────────────────────────────────────────────────────────

    private function authEntry($userId, ?string $name, string $action, string $description): void
    {
        AuditLog::entry([
            'user_id'     => $userId,
            'user_name'   => $name,
            'module'      => 'Auth',
            'action'      => $action,
            'description' => $description,
        ]);
        $this->written++;
    }

    private function write(array $e): void
    {
        AuditLog::entry([
            'user_id'      => $e['actor'][0],
            'user_name'    => $e['actor'][1],
            'module'       => $e['module'],
            'action'       => $e['action'],
            'description'  => Str::limit($e['description'], 1000),
            'subject_type' => $e['id'] !== null ? $e['type'] : null,
            'subject_id'   => $e['id'],
        ]);
        $this->written++;
    }

    /**
     * More than three of the same change in one request is one line: forty
     * payroll rows written by one click are one thing somebody did.
     */
    private function collapse(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$entry['module'] . '|' . $entry['action'] . '|' . $entry['type']][] = $entry;
        }

        $out = [];
        foreach ($groups as $group) {
            if (count($group) <= 3) {
                array_push($out, ...$group);
                continue;
            }

            $first = $group[0];
            $noun  = Str::plural(self::NOUNS[$first['type']] ?? Str::lower(Str::headline($first['type'])));
            $verb  = Str::ucfirst($first['action']);

            $out[] = ['description' => "{$verb} " . count($group) . " {$noun}", 'id' => null] + $first;
        }

        return $out;
    }

    /** A write that no model saw — logged by the route it came in on. */
    private function fallback(Request $request, int $status): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || $request->is('api/*') || $status >= 400) {
            return;
        }

        // Refused by validation, or by a rule that answered with an error.
        if ($request->hasSession() && ($request->session()->has('errors') || $request->session()->has('error'))) {
            return;
        }

        $route = $request->route();
        if (! $route) {
            return;
        }

        $name = $route->getName() ?? $route->uri();
        foreach (self::QUIET as $quiet) {
            if ($name === $quiet || (str_ends_with($quiet, '.') && str_starts_with($name, $quiet))) {
                return;
            }
        }

        [$module, $action, $text] = self::ROUTES[$name] ?? $this->routeWords($name, $request->method(), $route->parameters());

        $ids = $request->input('ids');
        if (is_array($ids)) {
            $text .= ' (' . count($ids) . ' selected)';
        }

        $user = auth()->user();

        AuditLog::entry([
            'user_id'     => $user?->getAuthIdentifier(),
            'user_name'   => $user ? $this->nameOf($user) : 'System',
            'module'      => $module,
            'action'      => $action,
            'description' => $text,
        ]);
        $this->written++;
    }

    private function routeWords(string $name, string $method, array $params): array
    {
        $parts  = array_values(array_filter(preg_split('#[./]#', trim($name, '/')), fn ($p) => $p !== '' && ! str_starts_with($p, '{')));
        $module = Str::headline($parts[0] ?? 'System');
        $action = match ($method) {
            'DELETE'       => 'deleted',
            'PUT', 'PATCH' => 'updated',
            default        => 'submitted',
        };

        $what = Str::headline(implode(' ', array_slice($parts, 1)) ?: ($parts[0] ?? 'request'));
        $ref  = collect($params)->filter(fn ($v) => is_scalar($v))->map(fn ($v) => '#' . $v)->implode(', ');

        return [$module, $action, $module . ': ' . Str::lower($what) . ($ref ? " ({$ref})" : '')];
    }

    // ── Describing a change ─────────────────────────────────────────────────

    /** [action, sentence] for one model event. */
    private function describe(string $action, Model $m, string $type): array
    {
        if ($type === 'Attendance') {
            return $this->describeAttendance($action, $m);
        }

        $noun = self::NOUNS[$type] ?? Str::lower(Str::headline($type));
        $what = trim($noun . ' ' . $this->label($m));

        return match ($action) {
            'created'  => ['created', 'Created ' . $what],
            'restored' => ['restored', 'Restored ' . $what],
            'deleted'  => ['deleted', method_exists($m, 'isForceDeleting') && ! $m->isForceDeleting()
                              ? 'Moved ' . $what . ' to Removed'
                              : 'Deleted ' . $what],
            default    => ['updated', 'Updated ' . $what . ': ' . $this->diff($m)],
        };
    }

    /** Clock-ins read as what they are, not as "Updated attendance #4012". */
    private function describeAttendance(string $action, Model $a): array
    {
        $who  = $this->employeeName($a) ?? 'a worker';
        $time = fn ($v) => $v ? Carbon::parse($v)->format('g:i A') : '—';
        $on   = $a->date ? Carbon::parse($a->date)->format('M j, Y') : '';
        $sess = $a->session ? ' · ' . $a->session . ' session' : '';

        if ($action === 'created' && $a->time_in) {
            return ['time in', "Time in for {$who} at " . $time($a->time_in) . $sess];
        }

        if ($action === 'updated' && $a->wasChanged('time_out') && $a->time_out && count(array_diff(array_keys($a->getChanges()), self::NOISE, ['time_out', 'close_type', 'needs_review', 'close_reason'])) === 0) {
            $auto = $a->close_type === 'auto' ? ' (closed automatically)' : '';
            return ['time out', "Time out for {$who} at " . $time($a->time_out) . $auto . $sess];
        }

        return match ($action) {
            'deleted' => ['deleted', "Deleted attendance for {$who} on {$on}"],
            'created' => ['created', "Created attendance for {$who} on {$on}"],
            default   => ['updated', "Updated attendance for {$who} on {$on}: " . $this->diff($a)],
        };
    }

    private function label(Model $m): string
    {
        foreach (['name', 'title', 'code', 'username', 'reference'] as $key) {
            if (! array_key_exists($key, $m->getAttributes())) {
                continue;
            }
            $value = $m->getAttributes()[$key];
            if (is_string($value) && trim($value) !== '') {
                return '“' . Str::limit(trim($value), 60) . '”';
            }
        }

        $who = $this->employeeName($m);

        return $who ? 'for ' . $who : '#' . $m->getKey();
    }

    /** "daily rate 650 → 700, site 3 → 4", at most four fields. */
    private function diff(Model $m): string
    {
        $parts = [];

        foreach ($m->getChanges() as $key => $new) {
            if (in_array($key, self::NOISE, true)) {
                continue;
            }

            $field = str_replace('_', ' ', preg_replace('/_id$/', '', $key));

            if (in_array($key, self::SECRET, true) || in_array($key, $m->getHidden(), true)) {
                $parts[] = $field . ' changed';
                continue;
            }

            $bool    = $m->hasCast($key, ['bool', 'boolean']);
            $parts[] = $field . ' ' . $this->value($m->getRawOriginal($key), $bool) . ' → ' . $this->value($new, $bool);
        }

        if (! $parts) {
            return 'no visible change';
        }

        $more = count($parts) > 4 ? ' and ' . (count($parts) - 4) . ' more' : '';

        return implode(', ', array_slice($parts, 0, 4)) . $more;
    }

    private function value($v, bool $bool = false): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        if ($bool || is_bool($v)) {
            return $v ? 'yes' : 'no';
        }
        if ($v instanceof \DateTimeInterface) {
            return Carbon::instance($v)->format('M j, Y g:i A');
        }
        if (is_array($v) || is_object($v)) {
            return '(list)';
        }

        $s = trim((string) $v);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return Carbon::parse($s)->format('M j, Y');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $s)) {
            return Carbon::parse($s)->format('M j, Y g:i A');
        }

        return Str::limit($s, 40);
    }

    private function employeeName(Model $m): ?string
    {
        $id = $m->getAttributes()['employee_id'] ?? null;
        if (! $id) {
            return null;
        }

        return $this->names[$id] ??= Employee::withTrashed()->whereKey($id)->value('name');
    }

    // ── Who did it ──────────────────────────────────────────────────────────

    /** [user_id, user_name] for a change made during this request. */
    private function actor(): array
    {
        $r = $this->request;

        // A page that tidied data as it loaded, e.g. closing a session nobody
        // signed out of. The viewer did not do that; the system did.
        if ($r && in_array($r->method(), ['GET', 'HEAD'], true)) {
            return [null, 'System'];
        }

        if ($user = auth()->user()) {
            return [$user->getAuthIdentifier(), $this->nameOf($user)];
        }

        if ($r && $r->is('api/*')) {
            return [null, $this->kioskName($r)];
        }

        return [null, 'System'];
    }

    private function kioskName(Request $r): string
    {
        $code = $r->input('kiosk_code');
        $id   = $r->input('kiosk_id');

        if (! $code && $id) {
            $code = ctype_digit((string) $id) ? Kiosk::whereKey($id)->value('code') : (string) $id;
        }

        return $code ? 'Kiosk ' . $code : ($r->is('api/kiosk*') ? 'Kiosk' : 'System');
    }

    private function nameOf($user): ?string
    {
        return $user->name ?? $user->username ?? null;
    }
}
