<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Account Management — Admin-only CRUD over the system's login accounts.
 *
 * Admins hold every module; staff accounts get the day-to-day workforce and
 * payroll screens but never Settings or this page (see routes/web.php).
 *
 * Two invariants are enforced throughout:
 *   1. The system always keeps at least one active admin.
 *   2. Admins cannot demote, deactivate, or delete themselves — that is the
 *      quickest way to lock everyone out of the system.
 */
class AccountController extends Controller
{
    /** Listing: search by name/username/email, filter by role and status. */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $role   = $request->query('role');
        $status = $request->query('status');

        $accounts = User::query()
            ->with('creator:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($role, [User::ROLE_ADMIN, User::ROLE_STAFF], true),
                fn ($q) => $q->where('role', $role))
            ->when($status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('accounts.index', [
            'accounts' => $accounts,
            'search'   => $search,
            'role'     => $role,
            'status'   => $status,
            'stats'    => [
                'total'    => User::count(),
                'admins'   => User::where('role', User::ROLE_ADMIN)->count(),
                'staff'    => User::where('role', User::ROLE_STAFF)->count(),
                'inactive' => User::where('is_active', false)->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            $this->rules(),
            $this->messages()
        );

        User::create([
            'name'       => $data['name'],
            'username'   => $data['username'],
            'email'      => $data['email'] ?: null,
            'password'   => Hash::make($data['password']),
            'role'       => $data['role'],
            'is_active'  => $request->boolean('is_active', true),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('accounts.index')
            ->with('success', "Account for {$data['name']} created. They can sign in with the username \"{$data['username']}\".");
    }

    public function edit(User $account)
    {
        return view('accounts.edit', ['account' => $account]);
    }

    public function update(Request $request, User $account)
    {
        $isSelf = $account->id === Auth::id();

        $data = $request->validate(
            $this->rules($account),
            $this->messages()
        );

        // Guard the last admin, and stop an admin from demoting themselves.
        if ($account->isAdmin() && $data['role'] !== User::ROLE_ADMIN) {
            if ($isSelf) {
                return back()->withInput()->withErrors([
                    'role' => 'You cannot change your own role. Ask another administrator to do it.',
                ]);
            }
            if ($this->otherActiveAdmins($account) === 0) {
                return back()->withInput()->withErrors([
                    'role' => 'This is the only administrator left. Promote another account first.',
                ]);
            }
        }

        $isActive = $isSelf ? true : $request->boolean('is_active', true);

        if ($account->isAdmin() && ! $isActive && $this->otherActiveAdmins($account) === 0) {
            return back()->withInput()->withErrors([
                'is_active' => 'This is the only active administrator. Activate another admin first.',
            ]);
        }

        $account->fill([
            'name'      => $data['name'],
            'username'  => $data['username'],
            'email'     => $data['email'] ?: null,
            'role'      => $isSelf ? $account->role : $data['role'],
            'is_active' => $isActive,
        ]);

        // Password is optional on edit — only set when the Admin typed a new one.
        if (! empty($data['password'])) {
            $account->password = Hash::make($data['password']);
        }

        $account->save();

        return redirect()->route('accounts.index')
            ->with('success', "{$account->name}'s account was updated.");
    }

    /** Quick activate / deactivate from the list. */
    public function toggle(User $account)
    {
        if ($account->id === Auth::id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($account->is_active && $account->isAdmin() && $this->otherActiveAdmins($account) === 0) {
            return back()->with('error', 'This is the only active administrator — the system must keep one.');
        }

        $account->is_active = ! $account->is_active;
        $account->save();

        return back()->with('success', $account->is_active
            ? "{$account->name} can sign in again."
            : "{$account->name} has been deactivated and can no longer sign in.");
    }

    public function destroy(User $account)
    {
        if ($account->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($account->isAdmin() && $this->otherActiveAdmins($account) === 0) {
            return back()->with('error', 'This is the only administrator — the system must keep one.');
        }

        $name = $account->name;
        $account->delete();

        return redirect()->route('accounts.index')
            ->with('success', "{$name}'s account was deleted.");
    }

    /**
     * Validation rules shared by store and update. On update the password is
     * optional and the uniqueness checks ignore the account being edited.
     */
    private function rules(?User $account = null): array
    {
        $id = $account?->id;

        return [
            'name'     => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'min:3', 'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($id),
            ],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [
                $account ? 'nullable' : 'required',
                'confirmed',
                // The minimum is a system setting; the rule it composes is not.
                Password::min(SystemSetting::current()->password_min_length)->letters()->numbers(),
            ],
        ];
    }

    private function messages(): array
    {
        return [
            'username.regex'  => 'The username may only contain letters, numbers, dots, dashes and underscores.',
            'username.unique' => 'That username is already taken.',
            'email.unique'    => 'That email is already used by another account.',
        ];
    }

    /** How many *other* active admins exist besides the given account. */
    private function otherActiveAdmins(User $account): int
    {
        return User::where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->where('id', '!=', $account->id)
            ->count();
    }
}
