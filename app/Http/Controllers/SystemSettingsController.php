<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The settings that are not payroll: who the company says it is, and how strict
 * the login is. The payroll page answers for pay.
 *
 * One row behind two tabs. They are separate actions rather than one form split
 * in half, so each validates only what it posts — a bad session timeout must not
 * refuse a corrected address.
 */
class SystemSettingsController extends Controller
{
    public function about()
    {
        return view('settings.about', ['system' => SystemSetting::current()]);
    }

    public function security()
    {
        return view('settings.security', ['system' => SystemSetting::current()]);
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
    public function attendance()
    {
        return view('settings.attendance', ['system' => SystemSetting::current()]);
    }

    public function updateAttendance(Request $request)
    {
        $data = $request->validate([
            'expected_time_in'       => ['required', 'date_format:H:i'],
            'grace_period_minutes'   => ['required', 'integer', 'min:0', 'max:120'],

            // A day of zero hours would divide the daily rate by nothing.
            'standard_hours_per_day' => ['required', 'numeric', 'min:1', 'max:24'],

            'week_starts_on'         => ['required', 'integer', 'min:0', 'max:6'],
            'payroll_cycle'          => ['required', 'in:weekly,daily'],
        ], [
            'standard_hours_per_day.min' => 'A day has to buy at least an hour, or the hourly rate has no divisor.',
            'grace_period_minutes.max'   => 'Two hours of grace is not a grace period.',
        ]);

        // An unticked checkbox posts nothing, which is the off answer.
        $data['auto_count_overtime'] = $request->boolean('auto_count_overtime');

        $settings = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);

        return $this->save($settings, $data, 'system-settings.attendance');
    }

    /** Write the row and drop the memo, so the next read sees what was saved. */
    private function save(SystemSetting $settings, array $data, string $back)
    {
        $settings->fill($data)->save();
        SystemSetting::forget();

        return redirect()->route($back)->with('success', 'Saved.');
    }
}
