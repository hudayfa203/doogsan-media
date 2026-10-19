<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetSiteLocale
{
    public function handle(Request $request, Closure $next, string $locale): Response
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);
        View::share('locale', $locale);

        $response = $next($request);

        // Remember the reader's edition choice for the root-domain redirect.
        if (method_exists($response, 'withCookie') && $request->cookie('edition') !== $locale) {
            $response->withCookie(cookie('edition', $locale, 60 * 24 * 365, null, null, null, false, false, 'Lax'));
        }

        return $response;
    }
}
