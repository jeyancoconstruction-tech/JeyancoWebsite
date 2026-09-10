<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Make sure public/storage points at storage/app/public.
     *
     * Laravel creates this link from composer's post-autoload-dump. Railway
     * never runs it: the build log says
     *
     *     composer install --optimize-autoloader --no-scripts --no-interaction
     *
     * so no composer script has ever executed in production, and public/storage
     * has never existed there — which is why no uploaded photo was reachable
     * over the web, on any deploy, since the day photos were added. Doing it in
     * composer.json cannot fix that; it has to happen at runtime.
     *
     * Costs one stat per worker process. FrankenPHP keeps workers alive, so in
     * practice this runs once after a deploy and then never again.
     *
     * It does NOT make photos survive a deploy: /app/storage is on the
     * container's overlay filesystem, so the files themselves are still wiped
     * every time. That needs a Railway volume mounted at
     * /app/storage/app/public — mounting at /app/storage instead would shadow
     * storage/framework/{cache,sessions,views} and take the app down.
     */
    private function ensureStorageLink(): void
    {
        static $checked = false;

        if ($checked) {
            return;
        }
        $checked = true;

        $link = public_path('storage');

        // is_link as well as file_exists: file_exists() is false for a symlink
        // whose target is missing, and symlink() would then fail on the link
        // that is already sitting there.
        if (is_link($link) || file_exists($link)) {
            return;
        }

        try {
            $target = storage_path('app/public');

            if (! is_dir($target)) {
                mkdir($target, 0755, true);
            }

            // Silenced deliberately. symlink() raises a warning rather than
            // throwing when it cannot link — on Windows without developer mode
            // it is "Permission denied" every time — and an unsilenced warning
            // here would be written on the first request of every worker, in a
            // situation the catch below cannot even see.
            @symlink($target, $link);
        } catch (Throwable) {
            // A read-only image, or no permission on public/. Photos 404,
            // which is the situation this is trying to improve — not a reason
            // to take every request down with it.
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureStorageLink();

        // In production (Railway) the public connection is always HTTPS, so
        // generate https:// links and form actions to avoid "not secure"
        // browser warnings on form submits.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // The session lifetime has to be set before the HTTP kernel starts the
        // session, which is here — it is the one setting that cannot wait until
        // something renders.
        $settings = $this->systemSettings();

        // Company identity, for the sidebar and everything that prints a
        // payslip. A composer rather than a share so it only reaches the views
        // that show it, and it resolves when the view renders rather than
        // capturing the row here — otherwise a save made during this request
        // would print the value it replaced.
        View::composer(
            ['layouts', 'auth.layout', 'payroll-records', 'payslips-batch'],
            fn ($view) => $view->with('company', $this->systemSettings())
        );

        if ($settings) {
            config(['session.lifetime' => $settings->session_timeout_minutes]);
        }
    }

    /**
     * The settings row, or null if it cannot be read.
     *
     * Boot runs before `migrate` has created the table on a fresh checkout, and
     * before the database exists at all on a container's first boot — so a
     * failure here has to be survivable, or the app cannot start far enough to
     * run the migration that would fix it.
     */
    private function systemSettings(): ?SystemSetting
    {
        try {
            if (Schema::hasTable('system_settings')) {
                return SystemSetting::current();
            }
        } catch (Throwable) {
            // No database yet, or no table. The defaults hold.
        }

        return null;
    }
}
