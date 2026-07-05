<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\LaborType;
use App\Models\Holiday;
use App\Models\Employee;
use App\Models\Attendance;
use App\Notifications\SettingsChanged;
use App\Notifications\EmployeeAlert;
use App\Notifications\HolidayReminder;
use App\Support\PhilippineHolidays;
use Carbon\Carbon;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $settings = Setting::first();
        $laborTypes = LaborType::all();

        // Holiday calendar: official PH holidays for the selected year merged
        // with manual entries (auto-recognised, admin-overridable).
        $holidayYear = (int) $request->input('year', now()->year);
        $holidayCalendar = Holiday::calendarFor($holidayYear);
        $holidayYearOptions = range(now()->year - 2, now()->year + 2);

        // Official holiday map for the next few years — powers the "recognised
        // holiday" auto-fill when an admin picks a date in the Add form.
        $officialMap = [];
        foreach (range(now()->year - 1, now()->year + 1) as $y) {
            foreach (PhilippineHolidays::forYear($y) as $date => $info) {
                $officialMap[$date] = [
                    'title' => $info['title'],
                    'type'  => PhilippineHolidays::typeLabel($info['type']),
                ];
            }
        }

        // ── Holiday reminders ──────────────────────────────────────────────
        $user = auth()->user();

        $tomorrow = now()->addDay()->toDateString();

        $upcoming = collect($holidayCalendar)->filter(
            fn ($holiday) => $holiday['date'] === $tomorrow
        )->values();

        foreach ($upcoming as $holiday) {
            HolidayReminder::fireOnce(
                $user,
                'upcoming_holiday',
                'Upcoming Holiday Tomorrow',
                "{$holiday['title']} is tomorrow.",
                $tomorrow
            );
        }

        return view('settings.index', compact(
            'settings', 'laborTypes', 'holidayCalendar', 'holidayYear', 'officialMap'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'ot_multiplier' => 'nullable|numeric|min:0|max:10',
            'bonus'         => 'nullable|numeric|min:0',
            'sss'           => 'nullable|numeric|min:0',
            'philhealth'    => 'nullable|numeric|min:0',
            'pagibig'       => 'nullable|numeric|min:0',
        ]);

        // Capture the previous Sunday rest-day state BEFORE saving so we can
        // detect a toggle and freeze history accordingly.
        $existing       = Setting::find(1);
        $oldRestEnabled = $existing?->sunday_rest_day_enabled ?? true;
        $newRestEnabled = $request->boolean('sunday_rest_day_enabled');

        // If the toggle changed, lock every PAST Sunday (before the current
        // payroll week) at its existing value so historical Payroll Records and
        // Payslips never recalculate. Only the current week + future follow the
        // new setting (their rest_day_applied stays null).
        if ($oldRestEnabled !== $newRestEnabled) {
            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
            Attendance::whereNull('rest_day_applied')
                ->whereDate('date', '<', $weekStart)
                ->whereRaw('DAYOFWEEK(date) = 1') // 1 = Sunday in MySQL
                ->update(['rest_day_applied' => $oldRestEnabled]);
        }

        Setting::updateOrCreate(
            ['id' => 1],
            [
                'ot_multiplier'           => $request->ot_multiplier ?? 1.25,
                'bonus'                   => $request->bonus         ?? 0,
                'sunday_rest_day_enabled' => $newRestEnabled,
                'sss'                     => $request->sss           ?? 0,
                'philhealth'              => $request->philhealth    ?? 0,
                'pagibig'                 => $request->pagibig       ?? 0,
            ]
        );

        SettingsChanged::fireOnce(auth()->user(), 'rates_updated',
            'Payroll Rates Updated',
            'Global payroll settings (OT multiplier, SSS, PhilHealth, Pag-IBIG) have been changed.'
        );

        return redirect()->route('settings.index')->with('success', 'Settings updated!');
    }

    public function storeLaborType(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:labor_types,name',
            'daily_rate' => 'required|numeric|min:0',
        ], [
            'name.unique' => 'This labor type already exists. Please use a different name or update the existing one.',
        ]);

        $type = LaborType::create([
            'name' => $request->name,
            'daily_rate' => $request->daily_rate,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'html'    => view('settings._labor_type_row', compact('type'))->render(),
            ]);
        }

        return redirect()->route('settings.index', ['tab' => 'labor'])->with('success', 'Labor type added successfully!');
    }

    public function updateLaborType(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:labor_types,name,' . $id,
            'daily_rate' => 'required|numeric|min:0',
        ], [
            'name.unique' => 'This labor type name already exists. Please use a different name.',
        ]);

        $laborType = LaborType::findOrFail($id);
        $laborType->update([
            'name' => $request->name,
            'daily_rate' => $request->daily_rate,
        ]);

        // Keep employees on this labor type in sync with the configured rate
        // (hourly = daily ÷ 8) so their stored rate never drifts out of alignment.
        Employee::where('labor_type_id', $laborType->id)
            ->update(['rate_per_hour' => $laborType->daily_rate / 8]);

        return redirect()->route('settings.index', ['tab' => 'labor'])->with('success', 'Labor type updated successfully!');
    }

    public function deleteLaborType($id)
    {
        $laborType = LaborType::findOrFail($id);
        $name      = $laborType->name;

        // Keep employees assigned to this labor type — only clear their labor
        // type and position so the records stay intact and can be re-assigned.
        $affected = Employee::where('labor_type_id', $laborType->id)->count();

        if ($affected > 0) {
            Employee::where('labor_type_id', $laborType->id)->update([
                'labor_type_id' => null,
                'position'      => null,
            ]);
        }

        $laborType->delete();

        SettingsChanged::fireOnce(auth()->user(), 'labor_type_deleted',
            'Labor Type Deleted',
            "The \"{$name}\" labor type has been removed from the system."
        );

        // Alert the admin that some employees now have no labor type / position.
        if ($affected > 0) {
            auth()->user()->notify(new EmployeeAlert(
                'unassigned_labor_type',
                'Employees Need Updating',
                "{$affected} employee(s) lost their position when the \"{$name}\" labor type was deleted. "
                . 'Please assign them a new labor type and position.'
            ));
        }

        $message = $affected > 0
            ? "Labor type deleted. {$affected} employee(s) were kept but now need a new labor type and position."
            : 'Labor type deleted successfully!';

        return redirect()->route('settings.index', ['tab' => 'labor'])->with('success', $message);
    }

    /**
     * Add a global holiday date. Applies the holiday pay multiplier to all
     * employees for that date. Non-destructive: attendance logs are untouched.
     *
     * If the chosen date is a recognised official Philippine holiday, it is
     * stored as such (and re-activated); otherwise it is saved as a custom
     * holiday. Always created active.
     */
    public function storeHoliday(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'title' => 'nullable|string|max:100',
        ]);

        $date = Carbon::parse($request->date)->toDateString();
        $official = PhilippineHolidays::infoFor($date);

        Holiday::updateOrCreate(
            ['date' => $date],
            [
                'title'       => $request->title ?: ($official['title'] ?? null),
                'type'        => $official['type'] ?? 'custom',
                'is_official' => $official !== null,
                'is_active'   => true,
            ]
        );

        return redirect()->route('settings.index', ['tab' => 'holiday'])
            ->with('success', 'Holiday added successfully!');
    }

    /**
     * Enable / disable a holiday for payroll without deleting attendance data.
     *
     * - Official holiday with no row yet (active by default) → create a disable
     *   override. Disabling/enabling toggles is_active; re-enabling an official
     *   holiday removes the override row so it returns to its default state.
     * - Custom holiday → flip is_active.
     */
    public function toggleHoliday(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date     = Carbon::parse($request->date)->toDateString();
        $official = PhilippineHolidays::infoFor($date);
        $holiday  = Holiday::whereDate('date', $date)->first();
        $newState = true;

        if ($holiday) {
            if ($holiday->is_official && ! $holiday->is_active) {
                $holiday->delete();   // re-enable: remove disable override → auto-active
                $newState = true;
            } else {
                $holiday->is_active = ! $holiday->is_active;
                $holiday->save();
                $newState = $holiday->is_active;
            }
        } elseif ($official) {
            Holiday::create([
                'date'        => $date,
                'title'       => $official['title'],
                'type'        => $official['type'],
                'is_official' => true,
                'is_active'   => false,
            ]);
            $newState = false;
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'date' => $date, 'is_active' => $newState]);
        }

        return redirect()->route('settings.index', ['tab' => 'holiday', 'year' => substr($date, 0, 4)])
            ->with('success', 'Holiday status updated.');
    }

    /**
     * Enable or disable ALL holidays for a given year in one action.
     * Returns JSON so the calendar can rebuild without a page reload.
     */
    public function bulkToggleHolidays(Request $request)
    {
        $request->validate([
            'action' => 'required|in:enable,disable',
            'year'   => 'required|integer|min:2000|max:2100',
        ]);

        $year   = (int) $request->year;
        $enable = $request->action === 'enable';

        if ($enable) {
            // Delete disable-override rows for official holidays → restores auto-active default
            Holiday::whereYear('date', $year)->where('is_official', true)->where('is_active', false)->delete();
            // Re-enable any manually disabled custom holidays
            Holiday::whereYear('date', $year)->where('is_official', false)->update(['is_active' => true]);
        } else {
            // Official holidays: insert disable overrides for dates with no row yet
            $existing = Holiday::whereYear('date', $year)->get()
                ->keyBy(fn ($h) => Carbon::parse($h->date)->toDateString());

            $inserts = [];
            $now = now();
            foreach (PhilippineHolidays::forYear($year) as $date => $info) {
                $row = $existing->get($date);
                if (! $row) {
                    $inserts[] = [
                        'date' => $date, 'title' => $info['title'], 'type' => $info['type'],
                        'is_official' => true, 'is_active' => false,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                } elseif ($row->is_active) {
                    $row->update(['is_active' => false]);
                }
            }
            if ($inserts) {
                Holiday::insert($inserts);
            }
            // Disable custom holidays too
            Holiday::whereYear('date', $year)->where('is_official', false)->update(['is_active' => false]);
        }

        return response()->json([
            'success'  => true,
            'calendar' => Holiday::calendarFor($year),
        ]);
    }

    /**
     * AJAX endpoint: return the merged holiday calendar for any year.
     * Used by the calendar UI for year navigation without a full page reload.
     */
    public function holidayCalendar(Request $request)
    {
        $year = max(2000, min(2100, (int) $request->input('year', now()->year)));

        return response()->json([
            'year'     => $year,
            'calendar' => Holiday::calendarFor($year),
        ]);
    }

    /**
     * Edit the label of a custom (non-official) holiday.
     */
    public function editHoliday(Request $request, $id)
    {
        $request->validate(['title' => 'required|string|max:100']);

        $holiday = Holiday::findOrFail($id);

        if ($holiday->is_official) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Official holidays cannot be renamed.'], 422);
            }
            return back()->withErrors(['title' => 'Official holidays cannot be renamed.']);
        }

        $holiday->update(['title' => $request->title]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'holiday' => [
                    'id'          => $holiday->id,
                    'date'        => Carbon::parse($holiday->date)->toDateString(),
                    'title'       => $holiday->title,
                    'type'        => $holiday->type,
                    'is_official' => false,
                    'is_active'   => (bool) $holiday->is_active,
                ],
            ]);
        }

        return redirect()->route('settings.index', ['tab' => 'holiday'])->with('success', 'Holiday updated.');
    }

    /**
     * Remove a global holiday date (custom holidays / disable-overrides).
     */
    public function deleteHoliday($id)
    {
        $holiday = Holiday::findOrFail($id);
        $date    = Carbon::parse($holiday->date)->toDateString();
        $holiday->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'date' => $date]);
        }

        return redirect()->route('settings.index', ['tab' => 'holiday'])
            ->with('success', 'Holiday removed successfully!');
    }

    /**
     * Get labor type rates via AJAX
     */
    public function getLaborTypeRates($id)
    {
        $laborType = LaborType::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $laborType->id,
                'name' => $laborType->name,
                'daily_rate' => $laborType->daily_rate,
                'ot_rate' => $laborType->ot_rate,
                'hourly_rate' => $laborType->getHourlyRate(),
                'formatted_daily_rate' => $laborType->getFormattedDailyRate(),
                'formatted_ot_rate' => $laborType->getFormattedOTRate(),
                'formatted_hourly_rate' => $laborType->getFormattedHourlyRate(),
            ]
        ]);
    }
}