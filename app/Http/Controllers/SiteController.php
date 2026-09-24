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
        foreach (['location', 'latitude', 'longitude', 'geofence_radius'] as $field) {
            if ($request->exists($field)) {
                $site->{$field} = $request->input($field);
            }
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

    /** What store and update share. A pin is a pair or nothing. */
    private function rules(): array
    {
        return [
            'latitude'        => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'       => 'nullable|numeric|between:-180,180|required_with:latitude',
            'geofence_radius' => 'nullable|integer|between:' . Site::RADIUS_MIN . ',' . Site::RADIUS_MAX,
        ];
    }

    private function messages(): array
    {
        return [
            'name.required'           => 'Please enter the project name.',
            'name.unique'             => 'A site with this project name already exists.',
            'geofence_radius.between' => 'The on-site radius has to be between ' . Site::RADIUS_MIN . ' and ' . Site::RADIUS_MAX . ' metres.',
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
            'employees_count' => $employees,
        ];
    }
}
