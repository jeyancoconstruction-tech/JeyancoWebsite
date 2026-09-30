<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\KioskFeed;
use App\Support\KioskStatus;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The settings that are not payroll: who the company says it is, how the
 * screens look, how strict the sign-in is, how the kiosk records and what
 * admins are told — and, since 2026-09-26, the Audit Log — as the sections of
 * one page built to Michael's jeyanco-settings.html, row for row. The
 * payroll page answers for pay.
 *
 * One row behind the sections. Each still has its own save action, which
 * validates only what it posts; the page's one save bar sends every edited
 * section to updateAll, which checks them all first and then runs each
 * section's own save.
 *
 * Every save is written to the Audit Log as old value → new value, which is
 * what "Last saved by" and the Audit logs section read.
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
        'kiosk_idle_return_seconds'  => ['back to Attendance after', ' s'],
        'company_tin'                => ['TIN', ''],
        'accent_color'               => ['accent colour', ''],
        'table_density'              => ['table density', ''],
        'signin_intro'               => ['intro animation', ''],
        'google_sign_in'             => ['Google sign-in', ''],
        'kiosk_location_check'       => ['reject scans out of range', ''],
        'notify_missing_scans'       => ['missing scans', ''],
        'notify_remittances'         => ['remittance reminders', ''],
        'notify_payroll'             => ['payroll ready', ''],
        'notify_email'               => ['email copies', ''],
    ];

    /** Switches: saved as true/false, written to the Audit Log as on/off. */
    private const SWITCHES = [
        'signin_intro', 'google_sign_in', 'kiosk_location_check',
        'notify_missing_scans', 'notify_remittances', 'notify_payroll', 'notify_email',
    ];

    private const SECTIONS = [
        'system-settings.about'      => 'Company',
        'system-settings.security'   => 'Security',
        'system-settings.appearance' => 'Appearance',
        'system-settings.kiosk'      => 'Kiosk',
        'system-settings.notifications' => 'Notifications',
    ];

    /** The sections, in the order the page lists them. */
    public const SECTIONS_ON_PAGE = ['company', 'appearance', 'security', 'kiosk', 'notif', 'audit'];

    // Each old address opens the one page on its own section.
    public function about(Request $request)
    {
        return $this->page($request, 'company');
    }

    public function security(Request $request)
    {
        return $this->page($request, 'security');
    }

    public function appearance(Request $request)
    {
        return $this->page($request, 'appearance');
    }

    public function kiosk(Request $request)
    {
        return $this->page($request, 'kiosk');
    }

    public function notifications(Request $request)
    {
        return $this->page($request, 'notif');
    }

    /** The whole page: every section's data, opened on one of them. */
    private function page(Request $request, string $section)
    {
        $asked   = (string) $request->query('section', '');
        $section = in_array($asked, self::SECTIONS_ON_PAGE, true) ? $asked : $section;

        return view('settings.system', $this->common() + [
            'section' => $section,
            // Every shift's TIME IN opens this long before it starts; null when they differ.
            'opens'   => ($o = Shift::query()->pluck('time_in_opens_minutes')->map(fn ($m) => (int) ($m ?? 120))->unique())->count() === 1 ? $o->first() : null,
            'audit'   => app(AuditLogController::class)->feed($request),
            // Each kiosk and whether it is on, for the rail and the monitor.
            'kiosks'  => Kiosk::with('site')->orderBy('name')->get()->map(fn (Kiosk $k) => KioskStatus::line($k))->values(),
            // Where a kiosk can be set: the sites on the Sites page, as named there.
            'kioskSites' => Site::orderBy('name')->get(['id', 'name', 'location']),
        ]);
    }

    /**
     * Kiosks → Kiosk site: where a kiosk stands. The office sets it here —
     * the kiosk has no site buttons of its own since 2026-09-28 — and the
     * kiosk follows within one settings question (a few seconds). Takes
     * effect at once, outside the page's save bar: a kiosk being carried to
     * the next site should not wait on anything else being saved.
     */
    public function kioskSite(Request $request, Kiosk $kiosk)
    {
        $data = $request->validate(['site_id' => 'required|exists:sites,id']);
        $site = Site::findOrFail($data['site_id']);
        $was  = $kiosk->site;

        if (! $was || $was->id !== $site->id) {
            $kiosk->forceFill(['site_id' => $site->id])->save();
            AuditLog::record('kiosk', 'updated',
                "Kiosk {$kiosk->code} set to {$site->name}" . ($was ? " (was {$was->name})" : ''), $kiosk);
            \App\Support\Live::bump('kiosk', 'devices');
        }

        $line = KioskStatus::line($kiosk->fresh('site'));

        return $request->expectsJson()
            ? response()->json(['success' => true, 'kiosk' => $line])
            : redirect()->route('system-settings.kiosk')->with('success', "{$kiosk->name} is now at {$site->name}.");
    }

    /**
     * Kiosks → Remove: a device the company no longer has. Its attendance
     * stays — the records keep their site and only lose the kiosk they came
     * from. The last kiosk cannot be removed: the scans need one to land on.
     */
    public function destroyKiosk(Kiosk $kiosk)
    {
        if (Kiosk::count() <= 1) {
            return redirect()->route('system-settings.kiosk')->with('error', 'This is the only kiosk, so it stays.');
        }

        $name = $kiosk->name;
        AuditLog::record('kiosk', 'deleted', "Kiosk {$kiosk->code} ({$name}) removed", $kiosk);
        Cache::forget('kiosk_location_' . $kiosk->id);
        Cache::forget('kiosk_location_' . $kiosk->code);
        $kiosk->delete();
        \App\Support\Live::bump('kiosk', 'devices');

        return redirect()->route('system-settings.kiosk')->with('success', "{$name} removed.");
    }

    /** Kiosks → Add kiosk: a second device, set to a site from the start. */
    public function storeKiosk(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:60'],
            'code'    => ['required', 'string', 'max:40', 'alpha_dash', 'unique:kiosks,code'],
            'site_id' => ['required', 'exists:sites,id'],
        ], [
            'code.unique'     => 'Another kiosk already uses this code.',
            'code.alpha_dash' => 'Use letters, numbers, dashes and underscores only — the same code as KIOSK_CODE on the Pi.',
        ]);

        $kiosk = Kiosk::create($data + ['is_active' => true]);
        AuditLog::record('kiosk', 'created', "Kiosk {$kiosk->code} added at {$kiosk->site?->name}", $kiosk);
        \App\Support\Live::bump('kiosk', 'devices');

        return redirect()->route('system-settings.kiosk')->with('success', "{$kiosk->name} added. Set KIOSK_CODE = \"{$kiosk->code}\" on its Pi.");
    }

    /**
     * The page's one save bar. The edited sections come as sections[]; all
     * of them are validated before any is saved, so one bad value leaves
     * everything as it was. Then each section's own save runs, exactly as
     * when it had a page of its own.
     */
    public function updateAll(Request $request)
    {
        $save = [
            'company'    => 'updateAbout',
            'appearance' => 'updateAppearance',
            'security'   => 'updateSecurity',
            'kiosk'      => 'updateKiosk',
            'notif'      => 'updateNotifications',
        ];
        $dirty   = array_values(array_intersect(array_keys($save), (array) $request->input('sections', [])));
        $current = in_array($request->input('current'), self::SECTIONS_ON_PAGE, true) ? $request->input('current') : ($dirty[0] ?? 'company');

        $rules = $messages = [];
        foreach ($dirty as $section) {
            [$r, $m] = $this->rulesFor($section);
            $rules    += $r;
            $messages += $m;
        }
        if ($rules) {
            $request->validate($rules, $messages);
        }

        foreach ($dirty as $section) {
            $this->{$save[$section]}($request);
        }

        return redirect()->route('system-settings.about', ['section' => $current])->with('success', 'Settings saved');
    }

    /**
     * What each section's save accepts. One list per section, read by its
     * own save and by updateAll.
     *
     * @return array{0: array, 1: array}
     */
    private function rulesFor(string $section): array
    {
        return match ($section) {
            'company' => [[
                'company_name'    => ['required', 'string', 'max:120'],
                'company_tagline' => ['required', 'string', 'max:160'],
                'company_address' => ['nullable', 'string', 'max:255'],
                'company_tin'     => ['sometimes', 'nullable', 'string', 'max:40', 'regex:/^[0-9 -]*$/'],
                'logo'            => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            ], [
                'company_tin.regex' => 'A TIN is digits and dashes, like 000-000-000-000.',
                'logo.mimes'        => 'Use a PNG or JPG image.',
            ]],

            'security' => [[
                // A session that never expires is not a setting anybody wants by
                // accident, and one of a minute logs the office out mid-payroll.
                'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],

                // Below eight is shorter than the rule every password on file was
                // already made to meet, so raising it later would lock nobody out
                // but lowering it now would weaken accounts silently.
                'password_min_length'     => ['required', 'integer', 'min:8', 'max:64'],

                'max_login_attempts'      => ['required', 'integer', 'min:3', 'max:20'],
                'lockout_seconds'         => ['required', 'integer', 'min:30', 'max:3600'],
                'google_sign_in'          => ['sometimes', 'boolean'],
            ], [
                'session_timeout_minutes.max' => 'A day is the longest a session should be able to stay open.',
                'password_min_length.min'     => 'Eight is the shortest password the accounts on file were made to meet.',
                'max_login_attempts.min'      => 'Fewer than three locks people out for a typo.',
            ]],

            'appearance' => [[
                // 'system' follows each device's own light or dark setting.
                'default_theme' => ['required', 'in:dark,light,system'],
                'accent_color'  => ['sometimes', 'in:' . implode(',', array_keys(SystemSetting::ACCENTS))],
                'table_density' => ['sometimes', 'in:' . implode(',', array_keys(SystemSetting::DENSITIES))],
                'signin_intro'  => ['sometimes', 'boolean'],
                // No 'locale' rule: the Language picker is gone and the form does
                // not post one. Requiring it here would fail every save of this
                // page over a field it no longer has.
            ], []],

            'kiosk' => [[
                'kiosk_attendance_mode'      => ['required', 'in:' . implode(',', array_keys(SystemSetting::KIOSK_MODES))],
                // No duplicate-scan setting since 2026-09-27: the kiosk takes one
                // time in and one time out per session (KioskController::recordClock).
                'kiosk_idle_return_seconds'  => ['required', 'integer', 'min:15', 'max:600'],
                'kiosk_location_check'       => ['sometimes', 'boolean'],
                // Unknown fingerprints and Offline alert left the page on 2026-09-30
                // (Michael). Both alerts still run on their saved values; nothing
                // here writes them.
                // Written to every shift; empty leaves shifts that differ as they are.
                'kiosk_opens_minutes'        => ['sometimes', 'nullable', 'integer', 'min:15', 'max:240'],
            ], [
                'kiosk_attendance_mode.in'       => 'Choose Automatic or Worker picks.',
            ]],

            'notif' => [[
                'notify_missing_scans' => ['sometimes', 'boolean'],
                'notify_remittances'   => ['sometimes', 'boolean'],
                'notify_payroll'       => ['sometimes', 'boolean'],
                'notify_email'         => ['sometimes', 'boolean'],
            ], []],

            default => [[], []],
        };
    }

    public function updateAbout(Request $request)
    {
        $data = $this->switches($request->validate(...$this->rulesFor('company')));

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
        $data = $this->switches($request->validate(...$this->rulesFor('security')));

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        return $this->save($settings, $data, 'system-settings.security');
    }

    public function updateAppearance(Request $request)
    {
        $data = $this->switches($request->validate(...$this->rulesFor('appearance')));

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
        $data = $this->switches($request->validate(...$this->rulesFor('kiosk')));

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        // How early TIME IN opens is each shift's own; the setting writes it to all of them.
        $extra = [];
        $opens = $data['kiosk_opens_minutes'] ?? null;
        unset($data['kiosk_opens_minutes']);
        if ($opens !== null) {
            $was = Shift::query()->pluck('time_in_opens_minutes')->map(fn ($m) => (int) ($m ?? 120))->unique();
            if ($was->count() !== 1 || (int) $was->first() !== (int) $opens) {
                Shift::query()->update(['time_in_opens_minutes' => (int) $opens]);
                $extra[] = 'kiosk opens before shift ' . ($was->count() === 1 ? $was->first() : 'varied') . ' → ' . (int) $opens . ' min';
            }
        }

        return $this->save($settings, $data, 'system-settings.kiosk', $extra);
    }

    public function updateNotifications(Request $request)
    {
        $data = $this->switches($request->validate(...$this->rulesFor('notif')));

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        return $this->save($settings, $data, 'system-settings.notifications');
    }

    /**
     * "Sign out everyone": every session but this one ends on its next
     * request (EndRevokedSessions), and remember-me cookies stop working, so
     * nobody is let straight back in by one.
     */
    public function signOutAll(Request $request)
    {
        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);
        $now      = now();

        $settings->forceFill(['sessions_revoked_at' => $now])->save();
        SystemSetting::forget();

        // This session carries on.
        $request->session()->put('signed_in_at', $now->timestamp);

        User::whereKeyNot($request->user()->id)->update(['remember_token' => null]);

        // With sessions in the database they can simply go now.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where(fn ($w) => $w->whereNull('user_id')->orWhere('user_id', '!=', $request->user()->id))
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        AuditLog::record('Settings', 'updated', 'Security: signed out every other session', $settings);

        return redirect()->route('system-settings.about', ['section' => 'security'])
            ->with('success', 'Every other session was signed out.');
    }

    /** A switch arrives as "0" or "1"; it is kept and compared as true or false. */
    private function switches(array $data): array
    {
        foreach (self::SWITCHES as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = (bool) $data[$key];
            }
        }

        return $data;
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
    private function save(SystemSetting $settings, array $data, string $back, array $extra = [])
    {
        $changes = array_merge($this->changes($settings, $data), $extra);

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

            if (in_array($key, self::SWITCHES, true)) {
                $out[] = $label . ' ' . ($old ? 'on' : 'off') . ' → ' . ($new ? 'on' : 'off');
                continue;
            }

            if ($key === 'accent_color' || $key === 'table_density') {
                $out[] = $label . ' ' . Str::lower((string) ($old ?: '—')) . ' → ' . Str::lower((string) $new);
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
