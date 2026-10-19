<?php

use App\Http\Middleware\AdminAccess;
use App\Http\Middleware\ApplyRedirects;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetSiteLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Global, so old URLs that match no route (e.g. WordPress /2026/09/01/slug/) still redirect.
        $middleware->prepend(ApplyRedirects::class);

        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'site.locale' => SetSiteLocale::class,
            'admin' => AdminAccess::class,
            'twofactor' => EnsureTwoFactor::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
