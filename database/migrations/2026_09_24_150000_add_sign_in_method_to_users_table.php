<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How an account signs in, and its name in two parts.
 *
 * - first_name / last_name: the Create Account form asks for them separately.
 *   `name` stays, always "first last", because the whole app reads it.
 * - login_method: 'both', 'google' or 'password'. A Google-only account has
 *   no usable password; a password-only one cannot use Sign in with Google.
 * - google_linked_at: the first time the account signed in with Google —
 *   "Pending" in Users & Roles until then, "Linked" after.
 * - must_change_password: the admin set the password, so the person chooses
 *   their own before they can open anything.
 *
 * Existing accounts keep what they can do today: one with an email could
 * already use Sign in with Google, so it becomes 'both'; one without stays on
 * its password. An account that has already signed in with Google counts as
 * linked from that first entry in the Audit Log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('users', 'login_method')) {
                $table->string('login_method', 10)->default('password')->after('password');
            }
            if (! Schema::hasColumn('users', 'google_linked_at')) {
                $table->timestamp('google_linked_at')->nullable()->after('login_method');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('google_linked_at');
            }
        });

        $linked = Schema::hasTable('audit_logs')
            ? DB::table('audit_logs')
                ->where('module', 'Auth')->where('action', 'signed in')
                ->where('description', 'like', 'Signed in with Google%')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->selectRaw('user_id, MIN(created_at) as first_at')
                ->pluck('first_at', 'user_id')
            : collect();

        foreach (DB::table('users')->get(['id', 'name', 'email', 'first_name']) as $user) {
            $change = ['login_method' => filled($user->email) ? 'both' : 'password'];

            if ($user->first_name === null) {
                [$change['first_name'], $change['last_name']] = $this->split((string) $user->name);
            }

            if (isset($linked[$user->id])) {
                $change['google_linked_at'] = $linked[$user->id];
            }

            DB::table('users')->where('id', $user->id)->update($change);
        }
    }

    /**
     * "Maria Clara Santos" → Maria Clara / Santos; "Juan dela Cruz" → Juan /
     * dela Cruz. A copy of User::splitName(), so the migration does not change
     * if the model does.
     */
    private function split(string $name): array
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < 2) {
            return [$words[0] ?? '', null];
        }

        $particles = ['de', 'del', 'dela', 'delos', 'della', 'di', 'da', 'dos', 'das', 'la', 'las', 'los', 'san', 'santa', 'sta.', 'sto.', 'van', 'von', 'mc', 'y'];
        $at = count($words) - 1;
        while ($at > 1 && in_array(strtolower($words[$at - 1]), $particles, true)) {
            $at--;
        }

        return [implode(' ', array_slice($words, 0, $at)), implode(' ', array_slice($words, $at))];
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['first_name', 'last_name', 'login_method', 'google_linked_at', 'must_change_password'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
