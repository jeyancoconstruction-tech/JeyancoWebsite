<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The settings that are not payroll: who the company says it is, and how strict
 * the login is. The payroll page answers for pay.
 *
 * One row behind three tabs. They are separate actions rather than one form
 * split in thirds, so each validates only what it posts — a bad session
 * timeout must not refuse a corrected address.
 *
 * Every save is written to the Audit Log as old value → new value, which is
 * what "Last saved by" and Security's "Recent changes" read.
 */
class SystemSettingsController extends Controller
{
    /** How each field is named in the Audit Log, and the unit after its value. */
    private const FIELDS = [
        'company_name'            => ['name', ''],
        'company_tagline'         => ['line under the name', ''],
        'company_address'         => ['address', ''],
        'logo_path'               => ['logo', ''],
        'session_timeout_minutes' => ['session timeout', ' min'],
        'password_min_length'     => ['minimum password length', ' characters'],
        'max_login_attempts'      => ['failed sign-ins before lockout', ''],
        'lockout_seconds'         => ['lockout length', ' s'],
        'default_theme'           => ['default theme', ''],
        'kiosk_attendance_mode'      => ['attendance mode', ''],
        'kiosk_repeat_guard_seconds' => ['repeat scans ignored within', ' s'],
        'kiosk_idle_return_seconds'  => ['back to Attendance after', ' s'],
    ];

    private const SECTIONS = [
        'system-settings.about'      => 'Company',
        'system-settings.security'   => 'Security',
        'system-settings.appearance' => 'Appearance',
        'system-settings.kiosk'      => 'Kiosk',
    ];

    /** Any date will do for drawing a shift's day: only the times matter. */
    private const ANY_DAY = '2026-01-05';

    /** An account this long without a sign-in is flagged on the Security tab. */
    private const IDLE_DAYS = 90;

    public function about()
    {
        return view('settings.about', $this->common());
    }

    public function security()
    {
        $cut = now()->subDays(self::IDLE_DAYS);

        return view('settings.security', $this->common() + [
            'hygiene' => [
                'admins'   => User::where('role', User::ROLE_ADMIN)->where('is_active', true)->count(),
                'disabled' => User::where('is_active', false)->orderBy('name')->pluck('name'),
                'idle'     => User::where('is_active', true)
                    ->where(fn ($w) => $w->where('last_login_at', '<', $cut)
                        ->orWhere(fn ($n) => $n->whereNull('last_login_at')->where('created_at', '<', $cut)))
                    ->orderBy('name')->pluck('name'),
            ],
            'changes' => AuditLog::where('module', 'Settings')->where('description', 'like', 'Security:%')
                ->latest()->latest('id')->limit(3)->get(),
        ]);
    }

    public function appearance()
    {
        return view('settings.appearance', $this->common());
    }

    /**
     * How the attendance kiosk records a scan: with TIME IN / TIME OUT
     * buttons, or automatically from the scan alone. Shows how Automatic
     * reads each shift, and whether each kiosk has picked the setting up.
     */
    public function kiosk()
    {
        $shifts = Shift::query()->orderBy('crosses_midnight')->orderBy('id')->get()
            ->filter(fn (Shift $s) => $s->hasSchedule())
            ->values();

        // A worker with no shift of their own works the day crew's.
        $default = Shift::defaultForNewHire();
        $crew    = Employee::where('status', Employee::STATUS_ACTIVE)->get(['id', 'shift_id'])
            ->countBy(fn (Employee $e) => $e->shift_id ?? $default);

        $day = $shifts->firstWhere('crosses_midnight', false);

        return view('settings.kiosk', $this->common() + [
            'rulers'   => $shifts->map(fn (Shift $s) => $this->ruler($s, (int) ($crew[$s->id] ?? 0)))->all(),
            'examples' => $day ? $this->examples($day) : [],
            'cut'      => $day ? WorkSchedule::label(WorkSchedule::lunchCut($day->schedule(), self::ANY_DAY)) : '12:30 PM',
            'kiosks'   => Kiosk::with('site')->orderBy('name')->get()->map(fn (Kiosk $k) => $this->kioskRow($k))->all(),
            'changes'  => AuditLog::where('module', 'Settings')->where('description', 'like', 'Kiosk:%')
                ->latest()->latest('id')->limit(3)->get(),
        ]);
    }

    /** One shift's day as a bar: TIME IN opens · first half · break · second half · OT. */
    private function ruler(Shift $shift, int $workers): array
    {
        $s    = $shift->schedule();
        $w    = WorkSchedule::windows($s, self::ANY_DAY);
        $from = $w['AM'][0]->copy()->subMinutes((int) $s['opens']);
        $to   = $w['PM'][1]->copy()->addHour();
        $span = max(1, (int) $from->diffInMinutes($to, true));
        $pct  = fn (Carbon $at) => round($from->diffInMinutes($at, true) / $span * 100, 3);
        $cut  = WorkSchedule::lunchCut($s, self::ANY_DAY);
        $half = $shift->crosses_midnight ? ['FIRST', 'SECOND'] : ['AM', 'PM'];
        $l    = fn (Carbon $at) => WorkSchedule::label($at);

        return [
            'name'     => $shift->name,
            'workers'  => $workers,
            'segments' => [
                ['early', 0, $pct($w['AM'][0]), 'TIME IN opens'],
                ['work', $pct($w['AM'][0]), $pct($w['AM'][1]), $half[0] . ' · ' . $l($w['AM'][0]) . ' – ' . $l($w['AM'][1])],
                ['lunch', $pct($w['AM'][1]), $pct($w['PM'][0]), ''],
                ['work', $pct($w['PM'][0]), $pct($w['PM'][1]), $half[1] . ' · ' . $l($w['PM'][0]) . ' – ' . $l($w['PM'][1])],
                ['ot', $pct($w['PM'][1]), 100, 'OT'],
            ],
            'cut'   => $pct($cut),
            'ticks' => [
                [0, $l($from), 'first'],
                [$pct($cut), $l($cut), 'cut'],
                [$pct($w['PM'][1]), $l($w['PM'][1]), ''],
            ],
        ];
    }

    /** What a scan records at a few moments of the day shift, in Automatic. */
    private function examples(Shift $shift): array
    {
        $s   = $shift->schedule();
        $w   = WorkSchedule::windows($s, self::ANY_DAY);
        $cut = WorkSchedule::lunchCut($s, self::ANY_DAY);
        $l   = fn (Carbon $at) => WorkSchedule::label($at);
        [$amS, $amE, $pmS, $pmE] = [$w['AM'][0], $w['AM'][1], $w['PM'][0], $w['PM'][1]];

        return [
            [$l($amS->copy()->subMinutes(8)), 'No open time in', [['in', 'AM IN']], 'Early — paid hours start at ' . $l($amS)],
            [$l($amE->copy()->addMinutes(3)), 'An open AM time in', [['out', 'AM OUT']], 'Before the ' . $l($cut) . ' cut-off, so it closes the morning'],
            [$l($pmS->copy()->subMinutes(4)), 'No open time in', [['in', 'PM IN']], 'Paid hours start at ' . $l($pmS)],
            [$l($cut->copy()->addMinutes(11)), 'An open AM time in — forgot to scan out', [['auto', 'AM OUT ' . $l($amE) . ' · AUTO'], ['in', 'PM IN']], 'After the cut-off. The morning closes at ' . $l($amE) . ' for the office to review'],
            [$l($pmE->copy()->addMinutes(4)), 'An open PM time in', [['out', 'PM OUT']], 'Time past ' . $l($pmE) . ' counts as overtime'],
            [$l($pmE->copy()->addMinutes(40)), 'No open time in', [['none', 'NOTHING']], 'TIME IN closed at ' . $l($pmE) . ' for the ' . $shift->name . ' shift — the kiosk says so'],
            [$l($amS->copy()->subMinutes(6)), 'Scanned 2 minutes ago', [['none', 'NOTHING']], '“Already recorded TIME IN” — a repeat inside the guard below'],
        ];
    }

    /** A kiosk, and when it last read its settings. */
    private function kioskRow(Kiosk $kiosk): array
    {
        $read  = $kiosk->settingsReadAt();
        $beat  = Cache::get('kiosk_location_' . $kiosk->code)['last_seen'] ?? null;
        $heard = collect([$kiosk->last_seen_at, $beat ? Carbon::parse($beat) : null, $read])->filter()->max();

        return [
            'name'   => $kiosk->name ?: $kiosk->code,
            'code'   => $kiosk->code,
            'site'   => $kiosk->site?->name,
            'read'   => $read,
            'heard'  => $heard,
            'online' => $heard && $heard->greaterThan(now()->subMinutes(3)),
        ];
    }

    public function updateAbout(Request $request)
    {
        $data = $request->validate([
            'company_name'    => ['required', 'string', 'max:120'],
            'company_tagline' => ['required', 'string', 'max:160'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'logo'            => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        // The old file goes only once the new one is stored, so a failed upload
        // does not leave the payslips with no logo at all.
        if ($request->hasFile('logo')) {
            $old = $settings->logo_path;
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');

            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        unset($data['logo']);

        return $this->save($settings, $data, 'system-settings.about');
    }

    public function updateSecurity(Request $request)
    {
        $data = $request->validate([
            // A session that never expires is not a setting anybody wants by
            // accident, and one of a minute logs the office out mid-payroll.
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],

            // Below eight is shorter than the rule every password on file was
            // already made to meet, so raising it later would lock nobody out
            // but lowering it now would weaken accounts silently.
            'password_min_length'     => ['required', 'integer', 'min:8', 'max:64'],

            'max_login_attempts'      => ['required', 'integer', 'min:3', 'max:20'],
            'lockout_seconds'         => ['required', 'integer', 'min:30', 'max:3600'],
        ], [
            'session_timeout_minutes.max' => 'A day is the longest a session should be able to stay open.',
            'password_min_length.min'     => 'Eight is the shortest password the accounts on file were made to meet.',
            'max_login_attempts.min'      => 'Fewer than three locks people out for a typo.',
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        return $this->save($settings, $data, 'system-settings.security');
    }

    public function updateAppearance(Request $request)
    {
        $data = $request->validate([
            // 'system' follows each device's own light or dark setting.
            'default_theme' => ['required', 'in:dark,light,system'],
            // No 'locale' rule: the Language picker is gone and the form does
            // not post one. Requiring it here would fail every save of this
            // page over a field it no longer has.
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        // Whoever saved this has their own theme in their own browser, and it
        // outranks the default — so without this the page they land on looks
        // exactly as it did and the save reads as having done nothing. Saving
        // the default is taken as choosing it for yourself as well.
        return $this->save($settings, $data, 'system-settings.appearance')
            ->with('theme_changed', $data['default_theme']);
    }

    public function updateKiosk(Request $request)
    {
        $data = $request->validate([
            'kiosk_attendance_mode'      => ['required', 'in:' . implode(',', array_keys(SystemSetting::KIOSK_MODES))],
            // Under a minute, a finger held a moment too long can still read twice.
            'kiosk_repeat_guard_seconds' => ['required', 'integer', 'min:60', 'max:600'],
            'kiosk_idle_return_seconds'  => ['required', 'integer', 'min:15', 'max:600'],
        ], [
            'kiosk_attendance_mode.in'       => 'Choose Buttons or Automatic.',
            'kiosk_repeat_guard_seconds.min' => 'Under a minute, a finger held a moment too long can still read twice.',
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        return $this->save($settings, $data, 'system-settings.kiosk');
    }

    /** What every tab shows around its form: the hub's summaries and the last save. */
    private function common(): array
    {
        return [
            'system'    => SystemSetting::current(),
            'hub'       => [
                'accounts' => User::count(),
                'admins'   => User::where('role', User::ROLE_ADMIN)->count(),
            ],
            'lastSaved' => AuditLog::where('module', 'Settings')->latest()->latest('id')->first(),
        ];
    }

    /** Write the row, drop the memo, and log what moved. */
    private function save(SystemSetting $settings, array $data, string $back)
    {
        $changes = $this->changes($settings, $data);

        $settings->fill($data)->save();
        SystemSetting::forget();

        if ($changes) {
            AuditLog::record('Settings', 'updated', self::SECTIONS[$back] . ': ' . implode(', ', $changes), $settings);
        }

        return redirect()->route($back)->with('success', 'Saved.');
    }

    /** "session timeout 60 → 120 min" for each field that actually moved. */
    private function changes(SystemSetting $settings, array $data): array
    {
        $out = [];

        foreach ($data as $key => $new) {
            $old = $settings->getAttribute($key);

            if ((string) $old === (string) $new) {
                continue;
            }

            [$label, $unit] = self::FIELDS[$key] ?? [str_replace('_', ' ', $key), ''];

            if ($key === 'logo_path') {
                $out[] = $old ? 'logo replaced' : 'logo uploaded';
                continue;
            }

            if ($key === 'kiosk_attendance_mode') {
                $name  = fn ($v) => SystemSetting::KIOSK_MODES[$v] ?? Str::ucfirst((string) $v);
                $out[] = "{$label} {$name($old)} → {$name($new)}";
                continue;
            }

            if (is_numeric($old) && is_numeric($new)) {
                $out[] = "{$label} {$old} → {$new}{$unit}";
                continue;
            }

            $word  = fn ($v) => ($v === null || $v === '') ? '—' : '“' . Str::limit((string) $v, 50) . '”';
            $out[] = $label . ' ' . $word($old) . ' → ' . $word($new);
        }

        return $out;
    }
}
