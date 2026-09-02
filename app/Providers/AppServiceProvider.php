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
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
