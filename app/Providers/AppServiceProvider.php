<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\Seo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Seo::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('components.pagination');
        View::composer('layouts.site', \App\View\Composers\SiteComposer::class);

        // Permission keys double as gates: @can('articles.publish')
        Gate::before(function ($user, string $ability) {
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });

        RateLimiter::for('forms', fn (Request $r) => [
            Limit::perMinute(5)->by($r->ip()),
            Limit::perDay(40)->by($r->ip()),
        ]);
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(6)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('beacon', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(240)->by($r->ip()));

        // Mail sender & contact address are editable in Admin → Settings.
        try {
            if ($from = Setting::get('mail_from_address')) {
                config(['mail.from.address' => $from]);
            }
        } catch (\Throwable) {
        }
    }
}
