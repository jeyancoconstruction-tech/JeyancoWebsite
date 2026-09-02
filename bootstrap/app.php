<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Railway's load balancer the app receives requests over HTTP
        // internally while the public connection is HTTPS. Trust the proxy so
        // Laravel honours X-Forwarded-Proto and generates https:// URLs.
        $middleware->trustProxies(at: '*');

        // The system's language, resolved per request rather than at boot: a
        // long-lived process would otherwise keep the language it started with.
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
            'active'   => \App\Http\Middleware\EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A login page left open past the session lifetime submits a stale CSRF
        // token. The default response is a bare "419 Page Expired" screen with
        // no way forward, which reads as a broken login. Hand back a fresh form
        // with an explanation instead.
        // Laravel rewrites the TokenMismatchException into a 419 HttpException
        // before render callbacks run, so the status code is what to match on.
        $exceptions->render(function (HttpExceptionInterface $e, $request) {
            if ($e->getStatusCode() === 419 && $request->isMethod('POST') && $request->is('login')) {
                return redirect()->route('login')
                    ->withErrors(['username' => 'Your session expired for security. Please sign in again.'])
                    ->withInput($request->only('username', 'remember'));
            }
        });
    })->create();
