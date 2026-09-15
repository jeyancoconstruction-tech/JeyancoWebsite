<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Http\Request;

/**
 * The one people screen: who has an account, which role each one carries, and
 * what that role opens.
 *
 * Creating an account and editing its details still happen on Account
 * Management's forms; this page lists the accounts, changes roles, and holds
 * the edit, deactivate and delete actions in its inspector.
 */
class UserRoleController extends Controller
{
    /** An account this long without a sign-in is flagged as idle. */
    public const IDLE_DAYS = 90;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $role   = (string) $request->query('role', '');
        $status = (string) $request->query('status', '');
        $cut    = now()->subDays(self::IDLE_DAYS);

        $users = User::with('creator:id,name')
            ->when(array_key_exists($role, User::ROLES), fn ($q) => $q->where('role', $role))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'disabled', fn ($q) => $q->where('is_active', false))
            ->when($status === 'idle', fn ($q) => $this->idle($q, $cut))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $selected = $request->filled('account')
            ? User::with('creator:id,name')->find($request->query('account'))
            : $users->first();

        $counts = User::selectRaw('role, COUNT(*) as n')->groupBy('role')->pluck('n', 'role');

        // role => '101000001' — one character per module, in Modules::labels() order.
        $fingerprints = [];
        foreach (array_keys(User::ROLES) as $key) {
            $allowed = Modules::forRole($key);
            $fingerprints[$key] = collect(array_keys(Modules::labels()))
                ->map(fn ($m) => in_array($m, $allowed, true) ? '1' : '0')
                ->implode('');
        }

        return view('users-roles.index', [
            'users'        => $users,
            'selected'     => $selected,
            'roles'        => User::ROLES,
            'modules'      => Modules::labels(),
            'groups'       => Modules::groups(),
            'adminOnly'    => array_values(array_filter(array_keys(Modules::labels()), [Modules::class, 'isAdminOnly'])),
            'fingerprints' => $fingerprints,
            'counts'       => $counts,
            'stats'        => [
                'total'    => User::count(),
                'active'   => User::where('is_active', true)->count(),
                'disabled' => User::where('is_active', false)->count(),
                'idle'     => $this->idle(User::query(), $cut)->count(),
                'admins'   => (int) ($counts[User::ROLE_ADMIN] ?? 0),
            ],
            'idleCut'        => $cut,
            'lastRoleChange' => AuditLog::where('module', 'Users')
                ->where('description', 'like', 'Changed % from % to %')
                ->latest()->latest('id')->first(),
            'history'        => $selected
                ? AuditLog::where('subject_type', 'User')->where('subject_id', $selected->id)
                    ->latest()->latest('id')->limit(5)->get()
                : collect(),
            'filters'        => ['q' => $search, 'role' => $role, 'status' => $status],
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

    /** Signed in longer ago than the cut-off — or never, on an account that old. */
    private function idle($query, $cut)
    {
        return $query->where(fn ($w) => $w
            ->where('last_login_at', '<', $cut)
            ->orWhere(fn ($n) => $n->whereNull('last_login_at')->where('created_at', '<', $cut)));
    }
}
