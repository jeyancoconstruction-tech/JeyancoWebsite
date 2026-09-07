<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Http\Request;

/**
 * Roles, and what each one may open.
 *
 * Accounts themselves — creating, disabling, resetting a password — stay in
 * the existing Account Management screen, which is untouched. This is the
 * permission side of the same data: which role an account carries, and the
 * matrix that role implies.
 */
class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('creator')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%' . $request->q . '%')
                ->orWhere('username', 'like', '%' . $request->q . '%')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // role => [module => allowed], for the matrix table.
        $matrix = [];
        foreach (array_keys(User::ROLES) as $role) {
            $allowed = Modules::forRole($role);
            foreach (Modules::labels() as $key => $label) {
                $matrix[$role][$key] = in_array($key, $allowed, true);
            }
        }

        return view('users-roles.index', [
            'users'   => $users,
            'roles'   => User::ROLES,
            'modules' => Modules::labels(),
            'matrix'  => $matrix,
            'counts'  => User::selectRaw('role, COUNT(*) as n')->groupBy('role')->pluck('n', 'role'),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => 'required|in:' . implode(',', array_keys(User::ROLES)),
        ]);

        // Losing the last administrator locks everyone out of Settings,
        // Account Management and this screen, with no way back in-app.
        if ($user->isAdmin() && $data['role'] !== User::ROLE_ADMIN) {
            $others = User::where('role', User::ROLE_ADMIN)->where('id', '!=', $user->id)->count();
            if ($others === 0) {
                return back()->with('error', 'This is the only administrator. Promote another account first.');
            }
        }

        $was = $user->role_label;
        $user->update($data);   // User::booted() keeps is_admin in step.

        AuditLog::record('Users', 'updated',
            'Changed ' . $user->name . ' from ' . $was . ' to ' . $user->fresh()->role_label, $user);

        return back()->with('success', $user->name . ' is now ' . $user->fresh()->role_label . '.');
    }
}
