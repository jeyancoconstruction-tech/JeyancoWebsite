<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
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
    ];

    private const SECTIONS = [
        'system-settings.about'      => 'Company',
        'system-settings.security'   => 'Security',
        'system-settings.appearance' => 'Appearance',
    ];

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
