<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
    /**
     * The list of accounts is Users & Roles now — one people screen, with the
     * account's edit, deactivate and delete actions in its inspector. This
     * address stays, because every save below and every old link lands here,
     * and passes its message and its filters along.
     */
    public function index(Request $request)
    {
        $request->session()->reflash();

        $filters = array_filter([
            'q'      => $request->query('q'),
            'role'   => $request->query('role'),
            'status' => $request->query('status') === 'inactive' ? 'disabled' : $request->query('status'),
        ]);

        return redirect()->route('users-roles.index', $filters);
    }

    public function create()
    {
        return view('accounts.create', ['googleSignIn' => AuthController::googleConfigured()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            $this->rules(),
            $this->messages()
        );

        $method = $data['login_method'];

        $account = User::create([
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'username'     => $data['username'],
            'email'        => ($data['email'] ?? null) ?: null,
            'login_method' => $method,
            'password'     => $this->passwordFor($method, $data['password'] ?? null),
            'must_change_password' => $method !== User::LOGIN_GOOGLE && $request->boolean('must_change_password'),
            'role'         => $data['role'],
            'is_active'    => $request->boolean('is_active', true),
            'created_by'   => Auth::id(),
        ]);

        $how = match ($method) {
            User::LOGIN_GOOGLE   => "They sign in with Google as {$account->email}.",
            User::LOGIN_PASSWORD => "They can sign in with the username \"{$account->username}\".",
            default              => "They can sign in with Google as {$account->email}, or with the username \"{$account->username}\".",
        };

        return redirect()->route('accounts.index')
            ->with('success', "Account for {$account->name} created. {$how}");
    }

    public function edit(User $account)
    {
        return view('accounts.edit', ['account' => $account, 'googleSignIn' => AuthController::googleConfigured()]);
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

        $method = $data['login_method'];

        $account->fill([
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'username'     => $data['username'],
            'email'        => ($data['email'] ?? null) ?: null,
            'login_method' => $method,
            'role'         => $isSelf ? $account->role : $data['role'],
            'is_active'    => $isActive,
        ]);

        // A different address is a different Google account: it has to sign
        // in once before it counts as linked again.
        if ($account->isDirty('email')) {
            $account->google_linked_at = null;
        }

        if ($method === User::LOGIN_GOOGLE) {
            // Google only: whatever password it had stops working.
            if ($account->getOriginal('login_method') !== User::LOGIN_GOOGLE) {
                $account->password = $this->passwordFor($method, null);
            }
            $account->must_change_password = false;
        } elseif (! empty($data['password'])) {
            // Password is optional on edit — only set when the Admin typed a new one.
            $account->password = Hash::make($data['password']);
            $account->must_change_password = $request->boolean('must_change_password');
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

        // A password is needed wherever one will be used and none exists yet:
        // always when creating, and on edit only when a Google-only account
        // takes on a password — it has none anybody knows.
        $passwordRequired = $account === null || $account->login_method === User::LOGIN_GOOGLE;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'login_method' => ['required', Rule::in(array_keys(User::LOGIN_METHODS)),
                // Google only, with no Google sign-in on this system, is an
                // account with no way in at all.
                function (string $attribute, $value, \Closure $fail) {
                    if ($value === User::LOGIN_GOOGLE && ! AuthController::googleConfigured()) {
                        $fail('Sign in with Google is not set up on this system yet, so a Google-only account could not sign in.');
                    }
                }],
            'username' => [
                'required', 'string', 'min:3', 'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($id),
            ],
            // Sign in with Google matches this address, so it is required
            // whenever Google is one of the ways in.
            'email'    => ['required_unless:login_method,' . User::LOGIN_PASSWORD, 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => [
                'exclude_if:login_method,' . User::LOGIN_GOOGLE,
                $passwordRequired ? 'required' : 'nullable',
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
            'email.required_unless' => 'Sign in with Google needs the Google account email.',
            'password.required'     => 'Set a password — this account will sign in with one.',
        ];
    }

    /**
     * The stored password. A Google-only account still needs something in the
     * column: a long random value nobody is ever told, so the password form
     * can never open it.
     */
    private function passwordFor(string $method, ?string $password): string
    {
        return Hash::make($method === User::LOGIN_GOOGLE || $password === null ? Str::random(64) : $password);
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
