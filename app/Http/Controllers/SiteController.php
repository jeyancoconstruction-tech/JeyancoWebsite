<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Site;

class SiteController extends Controller
{
    /** Render the Site Management module page. */
    public function index()
    {
        return view('sites.index');
    }

    /** Return all sites with employee count (JSON). */
    public function list()
    {
        $sites = Site::withCount('employees')->orderBy('name')->get();
        return response()->json(['success' => true, 'sites' => $sites]);
    }

    /** Create a new site (project) with a Google-Maps-selected location. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:100|unique:sites,name',
            'location'  => 'required|string|max:255',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [
            'name.required'     => 'Please enter the project name.',
            'name.unique'       => 'A site with this project name already exists.',
            'location.required' => 'Please select or enter the project location.',
        ]);

        $site = Site::create([
            'name'      => trim($validated['name']),
            'location'  => $validated['location']  ?? null,
            'latitude'  => $validated['latitude']  ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'site'    => [
                'id'              => $site->id,
                'name'            => $site->name,
                'location'        => $site->location,
                'latitude'        => $site->latitude,
                'longitude'       => $site->longitude,
                'employees_count' => 0,
            ],
        ]);
    }

    /**
     * Update a site. Employees keep their foreign key. Location fields are
     * optional — when omitted (e.g. a simple rename) the existing values are kept.
     */
    public function update(Request $request, $id)
    {
        $site = Site::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'required|string|max:100|unique:sites,name,' . $id,
            'location'  => 'nullable|string|max:255',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [
            'name.unique' => 'A site with this project name already exists.',
        ]);

        $site->update([
            'name'      => trim($validated['name']),
            'location'  => $request->input('location',  $site->location),
            'latitude'  => $request->input('latitude',  $site->latitude),
            'longitude' => $request->input('longitude', $site->longitude),
        ]);

        return response()->json([
            'success' => true,
            'site'    => [
                'id'        => $site->id,
                'name'      => $site->name,
                'location'  => $site->location,
                'latitude'  => $site->latitude,
                'longitude' => $site->longitude,
            ],
        ]);
    }

    /**
     * Delete a site. Employees assigned to it have site_id set to NULL
     * automatically by the nullOnDelete constraint on the foreign key.
     */
    public function destroy($id)
    {
        $site  = Site::withCount('employees')->findOrFail($id);
        $count = $site->employees_count;
        $site->delete();

        return response()->json([
            'success'         => true,
            'freed_employees' => $count,
        ]);
    }
}
