<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The settings that are not payroll: who the company says it is, and how strict
 * the login is. The payroll page next to it answers for pay.
 */
class SystemSettingsController extends Controller
{
    public function index()
    {
        return view('settings.system', [
            'system' => SystemSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name'            => ['required', 'string', 'max:120'],
            'company_tagline'         => ['required', 'string', 'max:160'],
            'company_address'         => ['nullable', 'string', 'max:255'],
            'logo'                    => ['nullable', 'image', 'max:2048'],

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

        $settings = SystemSetting::first() ?? new SystemSetting();

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

        $settings->fill($data)->save();
        SystemSetting::forget();

        return redirect()->route('system-settings.index')->with('success', 'System settings updated!');
    }
}
