<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Site;

class SiteController extends Controller
{
    /** Render the Site Management module page. */
    public function index()
    {
        return view('sites.index', [
            'radiusDefault' => (int) config('kiosk.geofence_radius'),
            'radiusMin'     => Site::RADIUS_MIN,
            'radiusMax'     => Site::RADIUS_MAX,
            'units'         => array_keys(Site::DURATION_UNITS),
        ]);
    }

    /** Return all sites with employee count (JSON). */
    public function list()
    {
        $sites = Site::withCount(['employees' => fn ($q) => $q->active()])->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'sites'   => $sites->map(fn (Site $s) => $this->present($s, $s->employees_count))->values(),
        ]);
    }

    /** Create a new site (project) with the spot picked on the map. */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules() + [
            'name'     => 'required|string|max:100|unique:sites,name',
            'location' => 'required|string|max:255',
        ], $this->messages() + [
            'location.required' => 'Please select or enter the project location.',
        ]);

        $site = Site::create([
            'name'            => trim($validated['name']),
            'location'        => $validated['location']        ?? null,
            'latitude'        => $validated['latitude']        ?? null,
            'longitude'       => $validated['longitude']       ?? null,
            'geofence_radius' => $validated['geofence_radius'] ?? null,
            'budget'          => $validated['budget']          ?? null,
            'duration'        => $validated['duration']        ?? null,
            'duration_unit'   => ($validated['duration'] ?? null) ? $validated['duration_unit'] : null,
        ]);

        return response()->json(['success' => true, 'site' => $this->present($site, 0)]);
    }

    /**
     * Update a site. Employees keep their foreign key. Every field but the
     * name is optional, and one the request leaves out keeps its value: the
     * dashboard map saves a pin without a radius, and a rename sends the name
     * alone, and neither should wipe what it did not send.
     */
    public function update(Request $request, $id)
    {
        $site = Site::findOrFail($id);

        $request->validate($this->rules() + [
            'name'     => 'required|string|max:100|unique:sites,name,' . $id,
            'location' => 'nullable|string|max:255',
        ], $this->messages());

        $site->name = trim($request->input('name'));
        foreach (['location', 'latitude', 'longitude', 'geofence_radius', 'budget', 'duration', 'duration_unit'] as $field) {
            if ($request->exists($field)) {
                $site->{$field} = $request->input($field);
            }
        }
        // A length cleared is cleared whole: no unit left behind with no number.
        if (! $site->duration) {
            $site->duration = null;
            $site->duration_unit = null;
        }
        $site->save();

        $employees = $site->employees()->active()->count();

        return response()->json(['success' => true, 'site' => $this->present($site, $employees)]);
    }

    /**
     * Delete a site. Employees assigned to it have site_id set to NULL
     * automatically by the nullOnDelete constraint on the foreign key.
     */
    public function destroy($id)
    {
        $site  = Site::withCount(['employees' => fn ($q) => $q->active()])->findOrFail($id);
        $count = $site->employees_count;
        $site->delete();

        return response()->json([
            'success'         => true,
            'freed_employees' => $count,
        ]);
    }

    /**
     * What store and update share. A pin is a pair or nothing; so is a
     * length, a number with its unit. The budget and the length are both
     * optional, and a length runs to ten years at most.
     */
    private function rules(): array
    {
        $units = array_keys(Site::DURATION_UNITS);

        return [
            'latitude'        => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'       => 'nullable|numeric|between:-180,180|required_with:latitude',
            'geofence_radius' => 'nullable|integer|between:' . Site::RADIUS_MIN . ',' . Site::RADIUS_MAX,
            'budget'          => 'nullable|numeric|min:0|max:999999999999.99',
            'duration'        => ['nullable', 'integer', 'min:1', function ($attribute, $value, $fail) {
                $max = Site::DURATION_UNITS[request()->input('duration_unit')] ?? null;
                if ($max !== null && (int) $value > $max) {
                    $fail('The expected length can be at most 10 years.');
                }
            }],
            'duration_unit'   => 'nullable|required_with:duration|in:' . implode(',', $units),
        ];
    }

    private function messages(): array
    {
        return [
            'name.required'               => 'Please enter the project name.',
            'name.unique'                 => 'A site with this project name already exists.',
            'geofence_radius.between'     => 'The on-site radius has to be between ' . Site::RADIUS_MIN . ' and ' . Site::RADIUS_MAX . ' metres.',
            'budget.numeric'              => 'The budget has to be an amount in pesos.',
            'budget.min'                  => 'The budget cannot be below zero.',
            'budget.max'                  => 'That budget is too large to keep.',
            'duration.integer'            => 'The expected length has to be a whole number.',
            'duration.min'                => 'The expected length has to be at least 1.',
            'duration_unit.required_with' => 'Pick days, weeks or months for the expected length.',
            'duration_unit.in'            => 'Pick days, weeks or months for the expected length.',
        ];
    }

    /**
     * One shape for every answer, so the list, a fresh save and the dashboard
     * map all read a site the same way. The radius is the one the GPS check
     * uses — the site's own, or the office-wide figure it falls back to.
     */
    private function present(Site $site, int $employees): array
    {
        return [
            'id'              => $site->id,
            'name'            => $site->name,
            'location'        => $site->location,
            'latitude'        => $site->latitude,
            'longitude'       => $site->longitude,
            'geofence_radius' => $site->geofenceRadius(),
            'budget'          => $site->budget !== null ? (float) $site->budget : null,
            'duration'        => $site->duration,
            'duration_unit'   => $site->duration ? $site->duration_unit : null,
            'length'          => $site->lengthLabel(),
            'employees_count' => $employees,
        ];
    }
}
